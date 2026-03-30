<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Adapters;

use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Contracts\HrsDriverInterface;
use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\DTOs\HrsEmployee;
use App\Domains\Integrations\DTOs\SyncResult;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Position;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HrsRestAdapter — اتصال به سامانه HRS از طریق REST API.
 *
 * این adapter انتظار دارد HRS endpoint های زیر را داشته باشد:
 * - GET /api/health — health check
 * - GET /api/employees/{employeeNumber} — یک کارمند
 * - GET /api/employees?modified_since=...&page=... — لیست کارمندان
 *
 * config مورد نیاز:
 * - base_url
 * - api_token (یا username/password برای basic auth)
 * - timeout
 * - field_mapping (نگاشت فیلد JSON پاسخ به DTO)
 */
class HrsRestAdapter extends AbstractIntegrationAdapter implements HrsDriverInterface
{
    public function getName(): string
    {
        return 'HRS REST API';
    }

    public function checkHealth(): HealthCheckResult
    {
        $start = microtime(true);
        try {
            $client = $this->makeClient();
            $response = $client->get('/api/health');
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($response->getStatusCode() === 200) {
                return HealthCheckResult::healthy(
                    message: 'HRS در دسترس است',
                    latencyMs: $latency,
                );
            }

            return HealthCheckResult::degraded(
                message: "HRS پاسخ غیرعادی داد: HTTP {$response->getStatusCode()}",
                latencyMs: $latency,
            );
        } catch (\Throwable $e) {
            return HealthCheckResult::down('HRS در دسترس نیست: ' . $e->getMessage());
        }
    }

    public function findEmployee(string $employeeNumber): ?HrsEmployee
    {
        try {
            $client = $this->makeClient();
            $response = $client->get("/api/employees/" . urlencode($employeeNumber));

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $response->getBody(), true);
            return $this->mapToDto($data);
        } catch (\Throwable $e) {
            Log::warning('HRS findEmployee failed', ['emp_no' => $employeeNumber, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function listEmployees(?\DateTimeInterface $modifiedSince = null): iterable
    {
        $client = $this->makeClient();
        $page = 1;
        $pageSize = (int) $this->config('page_size', 200);

        while (true) {
            $query = ['page' => $page, 'per_page' => $pageSize];
            if ($modifiedSince) {
                $query['modified_since'] = $modifiedSince->format('Y-m-d\TH:i:sP');
            }

            $response = $client->get('/api/employees', ['query' => $query]);
            $data = json_decode((string) $response->getBody(), true);
            $employees = $data['data'] ?? $data['employees'] ?? [];

            if (empty($employees)) {
                break;
            }

            foreach ($employees as $emp) {
                yield $this->mapToDto($emp);
            }

            // pagination: hard limit در صورت absence of pagination metadata
            if (count($employees) < $pageSize) {
                break;
            }
            $page++;
            if ($page > 1000) break; // safety
        }
    }

    public function syncEmployees(?\DateTimeInterface $modifiedSince = null): SyncResult
    {
        $result = new SyncResult();

        try {
            foreach ($this->listEmployees($modifiedSince) as $hrsEmployee) {
                try {
                    $this->syncOneEmployee($hrsEmployee, $result);
                } catch (\Throwable $e) {
                    $result->recordError($hrsEmployee->employeeNumber, $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $result->recordError('__hrs_traversal__', $e->getMessage());
        }

        return $result;
    }

    private function syncOneEmployee(HrsEmployee $hrs, SyncResult $result): void
    {
        DB::transaction(function () use ($hrs, $result) {
            // 1. واحد سازمانی (در صورت وجود)
            $orgUnitId = null;
            if ($hrs->departmentCode) {
                $unit = OrgUnit::firstOrCreate(
                    ['organization_id' => $this->provider->organization_id, 'code' => $hrs->departmentCode],
                    ['name' => $hrs->departmentName ?? $hrs->departmentCode, 'is_active' => true],
                );
                $orgUnitId = $unit->id;
            }

            // 2. پست سازمانی
            $positionId = null;
            if ($hrs->positionCode && $orgUnitId) {
                $position = Position::firstOrCreate(
                    ['organization_id' => $this->provider->organization_id, 'code' => $hrs->positionCode],
                    ['title' => $hrs->positionTitle ?? $hrs->positionCode, 'org_unit_id' => $orgUnitId],
                );
                $positionId = $position->id;
            }

            // 3. Employee
            $employee = Employee::updateOrCreate(
                [
                    'organization_id' => $this->provider->organization_id,
                    'employee_number' => $hrs->employeeNumber,
                ],
                [
                    'first_name' => $hrs->firstName,
                    'last_name' => $hrs->lastName,
                    'national_id' => $hrs->nationalId,
                    'email' => $hrs->email,
                    'phone' => $hrs->phone,
                    'org_unit_id' => $orgUnitId,
                    'position_id' => $positionId,
                    'is_active' => $hrs->isActive,
                    'hire_date' => $hrs->hireDate,
                    'termination_date' => $hrs->terminationDate,
                ],
            );

            $employee->wasRecentlyCreated
                ? $result->incrementCreated()
                : $result->incrementUpdated();
        });
    }

    private function makeClient(): Client
    {
        $headers = ['Accept' => 'application/json'];
        if ($token = $this->config('api_token')) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $config = [
            'base_uri' => rtrim((string) $this->config('base_url'), '/'),
            'timeout' => (int) $this->config('timeout', 30),
            'headers' => $headers,
            'verify' => (bool) $this->config('verify_ssl', true),
        ];

        if ($basicUser = $this->config('basic_username')) {
            $config['auth'] = [$basicUser, (string) $this->config('basic_password')];
        }

        return new Client($config);
    }

    private function mapToDto(array $data): HrsEmployee
    {
        $map = $this->config('field_mapping', []);
        $get = fn (string $key, mixed $default = null) => $data[$map[$key] ?? $key] ?? $default;

        return new HrsEmployee(
            employeeNumber: (string) $get('employee_number'),
            firstName: (string) $get('first_name', ''),
            lastName: (string) $get('last_name', ''),
            nationalId: $get('national_id'),
            email: $get('email'),
            phone: $get('phone'),
            departmentCode: $get('department_code'),
            departmentName: $get('department_name'),
            positionCode: $get('position_code'),
            positionTitle: $get('position_title'),
            managerEmployeeNumber: $get('manager_employee_number'),
            isActive: (bool) $get('is_active', true),
            hireDate: $get('hire_date') ? new \DateTimeImmutable((string) $get('hire_date')) : null,
            terminationDate: $get('termination_date') ? new \DateTimeImmutable((string) $get('termination_date')) : null,
            rawData: $data,
        );
    }
}
