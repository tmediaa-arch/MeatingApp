<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Tasks\Enums\TaskUpdateType;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskUpdate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * TaskEscalationService — مدیریت تاخیر و escalation وظایف.
 *
 * چندین کار:
 * 1. detectOverdueTasks — یافتن وظایفی که از مهلت گذشته‌اند
 * 2. escalate — ارسال notification به supervisor، سپس approver
 * 3. autoMarkOverdue — به‌روزرسانی فیلد is_overdue
 *
 * در فاز ۳، این Service از طریق scheduled command روزانه فراخوانی می‌شود
 * تا وظایف overdue را شناسایی و escalate کند.
 */
class TaskEscalationService
{
    /**
     * سطوح escalation:
     * level 0 = هیچ اقدامی، فقط overdue
     * level 1 = اطلاع به supervisor
     * level 2 = اطلاع به approver
     * level 3 = اطلاع به مدیر بالادست
     */
    private const ESCALATION_DELAYS_DAYS = [
        1 => 1,   // ۱ روز بعد از تاخیر
        2 => 3,   // ۳ روز بعد از تاخیر
        3 => 7,   // ۷ روز بعد از تاخیر
    ];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * اجرای کامل: detect + mark + escalate
     */
    public function runDaily(): array
    {
        $marked = $this->markOverdueTasks();
        $escalated = $this->escalateOverdueTasks();

        return [
            'marked_overdue' => $marked,
            'escalated' => $escalated,
        ];
    }

    /**
     * تنظیم is_overdue=true روی وظایفی که از مهلت گذشته‌اند.
     */
    public function markOverdueTasks(): int
    {
        return Task::query()
            ->open()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('is_overdue', false)
            ->update([
                'is_overdue' => true,
                'last_escalated_at' => null, // برای trigger escalation در گام بعد
            ]);
    }

    /**
     * Escalation لول‌به‌لول.
     */
    public function escalateOverdueTasks(): int
    {
        $tasks = Task::query()
            ->where('is_overdue', true)
            ->open()
            ->where(function ($q) {
                $q->where('escalation_level', '<', 3);
            })
            ->get();

        $count = 0;
        foreach ($tasks as $task) {
            $nextLevel = $task->escalation_level + 1;
            $daysOverdue = now()->startOfDay()->diffInDays($task->due_date);

            $requiredDelay = self::ESCALATION_DELAYS_DAYS[$nextLevel] ?? null;
            if ($requiredDelay === null) continue;
            if ($daysOverdue < $requiredDelay) continue;

            // قبلاً در این لول escalate شده؟
            if ($task->last_escalated_at
                && $task->last_escalated_at->diffInDays(now()) < 1
            ) {
                continue;
            }

            $this->performEscalation($task, $nextLevel);
            $count++;
        }
        return $count;
    }

    private function performEscalation(Task $task, int $level): void
    {
        $recipients = $this->getEscalationRecipients($task, $level);

        if (empty($recipients)) {
            Log::warning("No recipients for task {$task->task_number} at level {$level}");
            return;
        }

        // ارسال notification
        foreach ($recipients as $userId) {
            $this->dispatcher->send(
                templateKey: 'task.escalated',
                recipient: $userId,
                variables: [
                    'task_number' => $task->task_number,
                    'task_title' => $task->title,
                    'days_overdue' => now()->startOfDay()->diffInDays($task->due_date),
                    'assignee_name' => $task->assignee?->full_name ?? '—',
                    'escalation_level' => $level,
                ],
                notifiable: $task,
            );
        }

        // ثبت update
        TaskUpdate::create([
            'task_id' => $task->id,
            'updater_user_id' => 1, // system user — در فاز بعد بهتر می‌شود
            'update_type' => TaskUpdateType::Escalation->value,
            'content' => "Escalation to level {$level} — " . count($recipients) . ' recipients',
            'metadata' => [
                'level' => $level,
                'recipients' => $recipients,
            ],
            'occurred_at' => now(),
        ]);

        // به‌روزرسانی task
        $task->update([
            'escalation_level' => $level,
            'last_escalated_at' => now(),
        ]);

        $this->auditService->log(
            event: 'task_escalated',
            auditable: $task,
            description: sprintf(
                "وظیفه '%s' به سطح %d ارجاع شد",
                $task->task_number,
                $level,
            ),
            context: [
                'level' => $level,
                'recipients' => $recipients,
            ],
            severity: 'warning',
        );
    }

    /**
     * گیرندگان escalation در هر سطح
     */
    private function getEscalationRecipients(Task $task, int $level): array
    {
        $recipients = [];

        switch ($level) {
            case 1: // supervisor
                if ($task->supervisor?->user_id) {
                    $recipients[] = $task->supervisor->user_id;
                }
                break;

            case 2: // approver + supervisor
                if ($task->approver?->user_id) {
                    $recipients[] = $task->approver->user_id;
                }
                if ($task->supervisor?->user_id) {
                    $recipients[] = $task->supervisor->user_id;
                }
                break;

            case 3: // مدیر بالادست + creator
                $recipients[] = $task->creator_user_id;
                if ($task->approver?->user_id) {
                    $recipients[] = $task->approver->user_id;
                }
                // در فاز ۴ از org_units به‌صورت پویا parent unit برمی‌گردد
                break;
        }

        return array_unique(array_filter($recipients));
    }

    /**
     * گزارش وضعیت overdue (برای دشبورد)
     */
    public function getOverdueStats(?int $organizationId = null): array
    {
        $query = Task::query()->open()->where('is_overdue', true);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return [
            'total_overdue' => (clone $query)->count(),
            'level_0' => (clone $query)->where('escalation_level', 0)->count(),
            'level_1' => (clone $query)->where('escalation_level', 1)->count(),
            'level_2' => (clone $query)->where('escalation_level', 2)->count(),
            'level_3' => (clone $query)->where('escalation_level', 3)->count(),
        ];
    }
}
