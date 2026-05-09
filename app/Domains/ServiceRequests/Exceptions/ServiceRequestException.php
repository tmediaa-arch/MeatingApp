<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Exceptions;

use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use DomainException;

class ServiceRequestException extends DomainException
{
    public static function invalidTransition(ServiceRequestStatus $from, ServiceRequestStatus $to): self
    {
        return new self("تغییر وضعیت از '{$from->label()}' به '{$to->label()}' مجاز نیست.");
    }

    public static function alreadySubmitted(): self
    {
        return new self('این درخواست قبلاً ارسال شده است.');
    }

    public static function cannotReviewNonSubmitted(): self
    {
        return new self('فقط درخواست‌های submitted یا under_review قابل بررسی هستند.');
    }

    public static function notApproved(): self
    {
        return new self('فقط درخواست‌های تأیید شده قابل شروع هستند.');
    }

    public static function pastDueDate(): self
    {
        return new self('زمان مورد نیاز نمی‌تواند در گذشته باشد.');
    }

    public static function notAuthorized(): self
    {
        return new self('شما مجاز به انجام این عمل نیستید.');
    }

    public static function alreadyCompleted(): self
    {
        return new self('این درخواست قبلاً تکمیل شده است.');
    }
}
