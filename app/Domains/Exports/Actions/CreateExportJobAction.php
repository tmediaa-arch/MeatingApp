<?php

declare(strict_types=1);

namespace App\Domains\Exports\Actions;

use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Jobs\RunExportJob;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Identity\Models\User;

/**
 * CreateExportJobAction — یک ExportJob می‌سازد و آن را به queue می‌فرستد.
 *
 * این Action نقطه ورود استاندارد برای ساخت export از همه جای سامانه است
 * (Filament action ها، API endpoint ها، scheduler).
 */
class CreateExportJobAction
{
    public function execute(
        ExportType $type,
        string $format,
        array $params = [],
        ?User $requestedBy = null,
        ?int $organizationId = null,
        ?string $label = null,
        bool $dispatchImmediately = true,
    ): ExportJob {
        $job = ExportJob::create([
            'organization_id' => $organizationId ?? $requestedBy?->organization_id,
            'requested_by_user_id' => $requestedBy?->id,
            'export_type' => $type,
            'format' => $format,
            'input_params' => $params,
            'label' => $label,
            'status' => ExportStatus::Queued,
            'expires_at' => now()->addDays(7),
        ]);

        if ($dispatchImmediately) {
            RunExportJob::dispatch($job->id);
        }

        return $job;
    }
}
