<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Tasks\Models\TaskExtension;
use Illuminate\Support\Facades\DB;

class ReviewExtensionAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(
        TaskExtension $extension,
        User $reviewer,
        bool $approve,
        ?string $reviewNote = null,
    ): TaskExtension {
        if (!$extension->isPending()) {
            throw new \DomainException('این درخواست قبلاً بررسی شده است.');
        }

        return DB::transaction(function () use ($extension, $reviewer, $approve, $reviewNote) {
            $extension->update([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            if ($approve) {
                // به‌روزرسانی due_date روی task و reset is_overdue
                $extension->task->update([
                    'due_date' => $extension->requested_due_date,
                    'is_overdue' => false,
                    'escalation_level' => 0,
                ]);
            }

            // اطلاع به requester
            if ($extension->requested_by_user_id) {
                $this->dispatcher->send(
                    templateKey: 'task.extension_reviewed',
                    recipient: $extension->requested_by_user_id,
                    variables: [
                        'task_number' => $extension->task->task_number,
                        'status' => $approve ? 'تأیید' : 'رد',
                        'reviewer_name' => $reviewer->name,
                        'note' => $reviewNote ?? '',
                    ],
                    notifiable: $extension->task,
                );
            }

            $this->auditService->log(
                event: $approve ? 'task_extension_approved' : 'task_extension_rejected',
                auditable: $extension->task,
                description: sprintf(
                    "درخواست تمدید وظیفه '%s' %s",
                    $extension->task->task_number,
                    $approve ? 'تأیید' : 'رد',
                ),
                context: [
                    'extension_id' => $extension->id,
                    'new_due_date' => $extension->requested_due_date->format('Y-m-d'),
                ],
                severity: 'notice',
            );

            return $extension->fresh();
        });
    }
}
