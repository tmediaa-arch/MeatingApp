<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Resolutions\Enums\VoteValue;
use App\Domains\Resolutions\Exceptions\ResolutionException;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Resolutions\Models\ResolutionVote;
use Illuminate\Support\Facades\DB;

class CastVoteAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly AuthorizationService $authService,
    ) {
    }

    public function execute(
        Resolution $resolution,
        User $voter,
        VoteValue $vote,
        ?string $rationale = null,
        ?int $delegatedFromEmployeeId = null,
        ?int $delegationId = null,
    ): ResolutionVote {
        // اعتبارسنجی
        if (!$resolution->requires_voting) {
            throw ResolutionException::votingDoesNotRequire();
        }

        if (!$resolution->isVotingOpen()) {
            throw ResolutionException::votingNotOpen();
        }

        // یک employee → یک رأی
        $voterEmployeeId = $delegatedFromEmployeeId ?? $voter->employee_id;
        if (!$voterEmployeeId) {
            throw new \DomainException('کاربر شناسه employee ندارد.');
        }

        if ($this->hasAlreadyVoted($resolution, $voterEmployeeId)) {
            throw ResolutionException::alreadyVoted();
        }

        return DB::transaction(function () use (
            $resolution, $voter, $vote, $rationale,
            $voterEmployeeId, $delegatedFromEmployeeId, $delegationId,
        ) {
            // وزن رأی
            $weight = 1.0;
            if ($resolution->voting_type === 'weighted') {
                // در فاز ۴ از منطق وزن‌دهی استفاده می‌شود
                // فعلاً 1.0
            }

            // ثبت رأی
            $voteRecord = ResolutionVote::create([
                'resolution_id' => $resolution->id,
                'voter_employee_id' => $voterEmployeeId,
                'voter_user_id' => $voter->id,
                'vote' => $vote,
                'weight' => $weight,
                'delegated_from_employee_id' => $delegatedFromEmployeeId,
                'delegation_id' => $delegationId,
                'rationale' => $rationale,
                'voter_ip' => request()?->ip(),
                'voted_at' => now(),
            ]);

            // به‌روزرسانی counts روی resolution
            $this->updateVoteCounts($resolution);

            $this->auditService->log(
                event: 'resolution_vote_cast',
                auditable: $resolution,
                description: sprintf(
                    "رأی '%s' بر مصوبه '%s' ثبت شد",
                    $vote->label(),
                    $resolution->resolution_number,
                ),
                context: [
                    'vote' => $vote->value,
                    'is_proxy' => $delegatedFromEmployeeId !== null,
                ],
                severity: 'info',
            );

            return $voteRecord;
        });
    }

    private function hasAlreadyVoted(Resolution $resolution, int $employeeId): bool
    {
        return $resolution->votes()
            ->where('voter_employee_id', $employeeId)
            ->exists();
    }

    private function updateVoteCounts(Resolution $resolution): void
    {
        $votes = $resolution->votes()
            ->selectRaw('vote, SUM(weight) as total_weight, COUNT(*) as count_v')
            ->groupBy('vote')
            ->get()
            ->keyBy('vote');

        $resolution->update([
            'votes_for' => (int) ($votes->get(VoteValue::For->value)->total_weight ?? 0),
            'votes_against' => (int) ($votes->get(VoteValue::Against->value)->total_weight ?? 0),
            'votes_abstain' => (int) ($votes->get(VoteValue::Abstain->value)->total_weight ?? 0),
            'voters_total' => $resolution->votes()->count(),
        ]);
    }
}
