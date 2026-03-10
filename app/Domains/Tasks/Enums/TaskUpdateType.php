<?php
declare(strict_types=1);
namespace App\Domains\Tasks\Enums;

enum TaskUpdateType: string
{
    case Comment = 'comment';
    case StatusChange = 'status_change';
    case ProgressUpdate = 'progress_update';
    case Attachment = 'attachment';
    case Escalation = 'escalation';
    case Extension = 'extension';
    case Reassignment = 'reassignment';

    public function label(): string
    {
        return match ($this) {
            self::Comment => 'نظر',
            self::StatusChange => 'تغییر وضعیت',
            self::ProgressUpdate => 'به‌روزرسانی پیشرفت',
            self::Attachment => 'پیوست',
            self::Escalation => 'ارجاع به سطح بالاتر',
            self::Extension => 'تمدید مهلت',
            self::Reassignment => 'تغییر مجری',
        };
    }
}
