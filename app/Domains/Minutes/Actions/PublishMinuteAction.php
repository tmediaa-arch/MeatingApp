<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Minutes\Services\MinutePdfGenerator;
use App\Domains\Notifications\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * انتشار صورتجلسه:
 * 1. اعتبارسنجی: حتماً Signed باشد و هر دو امضا شده باشند
 * 2. تولید PDF و ذخیره
 * 3. تغییر وضعیت به Published
 * 4. ارسال notification به شرکت‌کنندگان
 */
class PublishMinuteAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionMinuteStatusAction $transitionAction,
        private readonly MinutePdfGenerator $pdfGenerator,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(Minute $minute, User $publisher): Minute
    {
        // 1. اعتبارسنجی
        if ($minute->status !== MinuteStatus::Signed) {
            throw MinuteException::cannotSignInStatus($minute->status);
        }

        if (!$minute->isFullySigned()) {
            throw MinuteException::notFullySigned();
        }

        return DB::transaction(function () use ($minute, $publisher) {
            // 2. تولید PDF
            $pdfResult = $this->pdfGenerator->generate($minute);

            // 3. به‌روزرسانی فیلدهای PDF و published metadata
            $minute->update([
                'pdf_path' => $pdfResult['path'],
                'pdf_hash' => $pdfResult['hash'],
                'pdf_generated_at' => now(),
                'published_at' => now(),
                'published_by_user_id' => $publisher->id,
            ]);

            // 4. تغییر وضعیت
            $this->transitionAction->execute(
                minute: $minute,
                newStatus: MinuteStatus::Published,
                reason: 'انتشار رسمی توسط ' . $publisher->name,
            );

            // 5. اطلاع‌رسانی به شرکت‌کنندگان
            $this->notifyParticipants($minute);

            // 6. audit
            $this->auditService->log(
                event: 'minute_published',
                auditable: $minute,
                description: sprintf(
                    "صورتجلسه '%s' منتشر شد",
                    $minute->minute_number,
                ),
                context: [
                    'pdf_path' => $pdfResult['path'],
                    'pdf_hash' => $pdfResult['hash'],
                ],
                severity: 'notice',
            );

            return $minute->fresh();
        });
    }

    private function notifyParticipants(Minute $minute): void
    {
        $userIds = MeetingParticipant::where('meeting_id', $minute->meeting_id)
            ->whereHas('employee.user')
            ->with('employee.user')
            ->get()
            ->pluck('employee.user.id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($userIds)) return;

        $this->dispatcher->sendBulk(
            templateKey: 'minute.published',
            userIds: $userIds,
            variables: [
                'minute_number' => $minute->minute_number,
                'minute_title' => $minute->title,
                'meeting_subject' => $minute->meeting->subject,
                'meeting_number' => $minute->meeting->meeting_number,
            ],
            notifiable: $minute,
        );
    }
}
