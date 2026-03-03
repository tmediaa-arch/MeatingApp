<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Minutes\Models\MinuteVersion;
use Illuminate\Support\Facades\DB;

/**
 * هر ویرایش صورتجلسه، یک snapshot جدید در minute_versions می‌سازد.
 * این الگو برای حفظ تاریخچه کامل و audit است.
 *
 * نکته: اگر امضایی روی نسخه قبلی شده، باید invalid شود
 * (در فاز ۳ ساده — فعلاً warning می‌دهیم؛ در فاز ۴ کامل می‌شود).
 */
class UpdateMinuteAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    private const UPDATABLE_FIELDS = [
        'title', 'content_html', 'content_text', 'summary',
        'key_decisions', 'metadata',
    ];

    public function execute(Minute $minute, array $data, ?string $changeSummary = null): Minute
    {
        if (!$minute->status->isEditable()) {
            throw MinuteException::cannotEditInStatus($minute->status);
        }

        $updates = array_intersect_key($data, array_flip(self::UPDATABLE_FIELDS));
        if (empty($updates)) {
            return $minute;
        }

        return DB::transaction(function () use ($minute, $updates, $changeSummary) {
            // محتوای فعلی برای تشخیص تغییر
            $contentChanged = isset($updates['content_html'])
                && $updates['content_html'] !== $minute->content_html;

            $newVersion = $minute->current_version;

            // اگر content تغییر کرده، نسخه جدید بساز
            if ($contentChanged) {
                $newVersion = $minute->current_version + 1;

                MinuteVersion::create([
                    'minute_id' => $minute->id,
                    'version_number' => $newVersion,
                    'content_html' => $updates['content_html'],
                    'content_text' => $updates['content_text'] ?? null,
                    'change_summary' => $changeSummary ?? 'بدون توضیح',
                    'created_by_user_id' => auth()->id(),
                    'created_at' => now(),
                ]);

                $updates['current_version'] = $newVersion;
            }

            $oldValues = array_intersect_key($minute->getAttributes(), $updates);
            $minute->update($updates);

            // audit
            $this->auditService->log(
                event: 'minute_updated',
                auditable: $minute,
                description: sprintf(
                    "صورتجلسه '%s' ویرایش شد%s",
                    $minute->minute_number,
                    $contentChanged ? " (نسخه {$newVersion})" : '',
                ),
                oldValues: $oldValues,
                newValues: $updates,
                context: [
                    'content_changed' => $contentChanged,
                    'change_summary' => $changeSummary,
                ],
                severity: 'info',
            );

            // در صورت تغییر content پس از signature، هشدار
            if ($contentChanged && $minute->signatures()->exists()) {
                $this->auditService->log(
                    event: 'minute_modified_after_signing',
                    auditable: $minute,
                    description: 'هشدار: محتوای صورتجلسه پس از امضا تغییر کرد. امضاها باید بررسی شوند.',
                    severity: 'warning',
                );
            }

            return $minute->fresh();
        });
    }
}
