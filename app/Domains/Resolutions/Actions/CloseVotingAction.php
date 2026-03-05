<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Exceptions\ResolutionException;
use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Support\Facades\DB;

class CloseVotingAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionResolutionStatusAction $transitionAction,
    ) {
    }

    public function execute(Resolution $resolution): Resolution
    {
        if (!$resolution->requires_voting) {
            throw ResolutionException::votingDoesNotRequire();
        }

        if (!$resolution->voting_opened_at) {
            throw ResolutionException::votingNotOpen();
        }

        return DB::transaction(function () use ($resolution) {
            $resolution->update([
                'voting_closed_at' => now(),
            ]);

            // تصمیم بر اساس نتایج
            $progress = $resolution->voting_progress;

            if (!$progress['quorum_reached']) {
                $newStatus = ResolutionStatus::Failed;
                $reason = sprintf(
                    'حد نصاب رسمیت رأی‌گیری حاصل نشد (%d از %d)',
                    $resolution->voters_total,
                    $resolution->quorum_required ?? 0,
                );
            } elseif ($progress['is_passing']) {
                $newStatus = ResolutionStatus::Approved;
                $reason = sprintf(
                    'مصوبه تأیید شد (%s%% موافق)',
                    $progress['percent_for'],
                );
            } else {
                $newStatus = ResolutionStatus::Failed;
                $reason = sprintf(
                    'مصوبه رأی نیاورد (%s%% موافق - زیر آستانه)',
                    $progress['percent_for'],
                );
            }

            $this->transitionAction->execute($resolution, $newStatus, $reason);

            if ($newStatus === ResolutionStatus::Approved) {
                $resolution->update([
                    'approved_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                ]);
            }

            $this->auditService->log(
                event: 'resolution_voting_closed',
                auditable: $resolution,
                description: sprintf(
                    "رأی‌گیری مصوبه '%s' پایان یافت: %s",
                    $resolution->resolution_number,
                    $reason,
                ),
                context: $progress,
                severity: 'notice',
            );

            return $resolution->fresh();
        });
    }
}
