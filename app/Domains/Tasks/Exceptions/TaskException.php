<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Exceptions;

use App\Domains\Tasks\Enums\TaskStatus;
use DomainException;

class TaskException extends DomainException
{
    public static function invalidStateTransition(TaskStatus $from, TaskStatus $to): self
    {
        return new self(sprintf(
            'انتقال وضعیت وظیفه از "%s" به "%s" مجاز نیست.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function cannotUpdateTerminal(TaskStatus $status): self
    {
        return new self(sprintf('وظیفه با وضعیت "%s" قابل به‌روزرسانی نیست.', $status->label()));
    }

    public static function notAssigned(): self
    {
        return new self('وظیفه هنوز به کسی ارجاع نشده است.');
    }

    public static function alreadyAssigned(): self
    {
        return new self('وظیفه از قبل ارجاع داده شده است.');
    }

    public static function dueDateInPast(): self
    {
        return new self('مهلت وظیفه نمی‌تواند در گذشته باشد.');
    }

    public static function extensionDateBeforeDue(): self
    {
        return new self('تاریخ تمدید باید بعد از مهلت اصلی باشد.');
    }

    public static function extensionAlreadyPending(): self
    {
        return new self('یک درخواست تمدید در حال انتظار برای این وظیفه وجود دارد.');
    }

    public static function notAssignee(): self
    {
        return new self('فقط مجری می‌تواند این عمل را انجام دهد.');
    }
}
