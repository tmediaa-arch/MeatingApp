<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTOs\HrsEmployee;
use App\Domains\Integrations\DTOs\SyncResult;

interface HrsDriverInterface extends IntegrationDriverInterface
{
    /**
     * دریافت اطلاعات یک کارمند
     */
    public function findEmployee(string $employeeNumber): ?HrsEmployee;

    /**
     * دریافت همه کارمندان (paginated)
     *
     * @return iterable<HrsEmployee>
     */
    public function listEmployees(?\DateTimeInterface $modifiedSince = null): iterable;

    /**
     * sync کامل کارمندان از HRS
     */
    public function syncEmployees(?\DateTimeInterface $modifiedSince = null): SyncResult;
}
