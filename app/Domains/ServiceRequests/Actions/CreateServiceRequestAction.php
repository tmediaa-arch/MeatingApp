<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Exceptions\ServiceRequestException;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Domains\ServiceRequests\Models\ServiceRequestUpdate;
use Illuminate\Support\Facades\DB;

/**
 * ایجاد یک ServiceRequest جدید.
 *
 * - شماره خودکار {ORG}-SRV-{YYYY}-####
 * - status اولیه: draft (یا submitted اگر submit_immediately=true)
 */
class CreateServiceRequestAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    /**
     * @param array $data
     *   - organization_id
     *   - type             (ServiceRequestType)
     *   - title
     *   - description?
     *   - meeting_id?
     *   - type_specific_data?
     *   - priority?        (default normal)
     *   - required_at      (DateTime)
     *   - estimated_duration_minutes?
     *   - requester_user_id
     *   - requester_employee_id?
     *   - requester_unit_id?
     *   - provider_unit_id?
     *   - estimated_cost?
     *   - submit_immediately?  (default false)
     */
    public function execute(array $data): ServiceRequest
    {
        // اعتبارسنجی: required_at نباید در گذشته باشد
        $requiredAt = $data['required_at'] instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($data['required_at'])
            : \Carbon\Carbon::parse($data['required_at']);

        if ($requiredAt->isPast()) {
            throw ServiceRequestException::pastDueDate();
        }

        return DB::transaction(function () use ($data, $requiredAt) {
            $submit = (bool) ($data['submit_immediately'] ?? false);

            $request = ServiceRequest::create([
                'organization_id' => $data['organization_id'],
                'request_number' => $this->generateRequestNumber($data['organization_id']),
                'type' => $data['type'] instanceof ServiceRequestType
                    ? $data['type']
                    : ServiceRequestType::from($data['type']),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'meeting_id' => $data['meeting_id'] ?? null,
                'type_specific_data' => $data['type_specific_data'] ?? [],
                'priority' => $data['priority'] ?? 'normal',
                'status' => $submit ? ServiceRequestStatus::Submitted : ServiceRequestStatus::Draft,
                'required_at' => $requiredAt,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'requester_user_id' => $data['requester_user_id'],
                'requester_employee_id' => $data['requester_employee_id'] ?? null,
                'requester_unit_id' => $data['requester_unit_id'] ?? null,
                'provider_unit_id' => $data['provider_unit_id'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'tags' => $data['tags'] ?? [],
                'submitted_at' => $submit ? now() : null,
            ]);

            ServiceRequestUpdate::create([
                'service_request_id' => $request->id,
                'update_type' => 'status_change',
                'from_value' => null,
                'to_value' => $request->status->value,
                'comment' => 'درخواست ایجاد شد',
                'actor_user_id' => $data['requester_user_id'],
            ]);

            $this->auditService->log(
                event: 'service_request_created',
                auditable: $request,
                description: sprintf(
                    'درخواست "%s" از نوع %s ایجاد شد',
                    $request->title,
                    $request->type->label(),
                ),
                context: ['request_number' => $request->request_number],
                severity: 'info',
            );

            return $request;
        });
    }

    /**
     * شماره خودکار به فرمت {ORG_CODE}-SRV-{YYYY}-####
     */
    private function generateRequestNumber(int $organizationId): string
    {
        $org = \App\Domains\Organization\Models\Organization::find($organizationId);
        $year = now()->year;

        $lastNumber = ServiceRequest::where('organization_id', $organizationId)
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->max('id') ?? 0;

        $nextNumber = $lastNumber + 1;
        $code = $org?->code ?? 'ORG';

        return sprintf('%s-SRV-%d-%04d', $code, $year, $nextNumber);
    }
}
