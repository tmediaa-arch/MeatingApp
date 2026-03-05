<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\ResolutionType;
use App\Domains\Resolutions\Exceptions\ResolutionException;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Resolutions\Models\ResolutionAssignee;
use Illuminate\Support\Facades\DB;

/**
 * ایجاد مصوبه از روی صورتجلسه.
 *
 * - فقط روی Minute در وضعیت Signed/Published مجاز است
 *   (یا Review اگر mode=draft تنظیم شده)
 * - شماره خودکار: ${ORG}-RES-${year}-####
 * - assignees در همین گام ایجاد می‌شوند
 */
class CreateResolutionAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array $data
     *   - title: string
     *   - content: string (HTML)
     *   - rationale: ?string
     *   - type: ResolutionType
     *   - priority: ?string
     *   - agenda_item_id: ?int
     *   - due_date: ?\DateTimeInterface
     *   - requires_voting: bool
     *   - voting_type: ?string
     *   - quorum_required: ?int
     *   - majority_threshold_percent: ?int
     *   - tags: ?array
     *   - assignees: array<['employee_id'|'org_unit_id', 'role', 'is_primary'?]>
     */
    public function execute(Minute $minute, array $data): Resolution
    {
        // اعتبارسنجی: minute حداقل signed باشد (یا در حال review برای draft)
        if (!in_array($minute->status, [
            MinuteStatus::Review,
            MinuteStatus::Signed,
            MinuteStatus::Published,
        ], true)) {
            throw ResolutionException::minuteNotSigned();
        }

        return DB::transaction(function () use ($minute, $data) {
            // شماره مصوبه
            $resolutionNumber = $this->generateResolutionNumber($minute->organization_id);

            $resolution = Resolution::create([
                'minute_id' => $minute->id,
                'meeting_id' => $minute->meeting_id,
                'organization_id' => $minute->organization_id,
                'resolution_number' => $resolutionNumber,
                'agenda_item_id' => $data['agenda_item_id'] ?? null,
                'title' => $data['title'],
                'content' => $data['content'],
                'rationale' => $data['rationale'] ?? null,
                'type' => $data['type'] ?? ResolutionType::Decision,
                'priority' => $data['priority'] ?? 'normal',
                'status' => ResolutionStatus::Draft,
                'requires_voting' => $data['requires_voting'] ?? false,
                'voting_type' => $data['voting_type'] ?? null,
                'quorum_required' => $data['quorum_required'] ?? null,
                'majority_threshold_percent' => $data['majority_threshold_percent'] ?? 50,
                'due_date' => $data['due_date'] ?? null,
                'tags' => $data['tags'] ?? [],
                'creator_user_id' => auth()->id() ?? ($data['creator_user_id'] ?? null),
            ]);

            // assignees
            if (!empty($data['assignees'])) {
                foreach ($data['assignees'] as $assigneeData) {
                    ResolutionAssignee::create([
                        'resolution_id' => $resolution->id,
                        'employee_id' => $assigneeData['employee_id'] ?? null,
                        'org_unit_id' => $assigneeData['org_unit_id'] ?? null,
                        'role' => $assigneeData['role'],
                        'is_primary' => $assigneeData['is_primary'] ?? false,
                    ]);
                }
            }

            $this->auditService->log(
                event: 'resolution_created',
                auditable: $resolution,
                description: sprintf(
                    "مصوبه '%s' در صورتجلسه '%s' ایجاد شد",
                    $resolution->resolution_number,
                    $minute->minute_number,
                ),
                context: [
                    'requires_voting' => $resolution->requires_voting,
                    'assignees_count' => count($data['assignees'] ?? []),
                ],
                severity: 'notice',
            );

            return $resolution;
        });
    }

    private function generateResolutionNumber(int $organizationId): string
    {
        $orgCode = \App\Domains\Organization\Models\Organization::find($organizationId)->code ?? 'ORG';
        $year = now()->year;
        $prefix = "{$orgCode}-RES-{$year}-";

        $last = Resolution::where('organization_id', $organizationId)
            ->where('resolution_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('resolution_number');

        $nextNum = $last
            ? ((int) substr($last, strrpos($last, '-') + 1)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNum);
    }
}
