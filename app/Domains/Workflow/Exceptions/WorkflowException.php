<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Exceptions;

use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use DomainException;

class WorkflowException extends DomainException
{
    public static function bpmnParseError(string $reason): self
    {
        return new self("خطای پارس BPMN: {$reason}");
    }

    public static function unsupportedElement(string $type): self
    {
        return new self("نوع عنصر BPMN پشتیبانی نمی‌شود: {$type}");
    }

    public static function noStartEvent(): self
    {
        return new self('فرایند هیچ start event ندارد.');
    }

    public static function multipleStartEvents(): self
    {
        return new self('فرایند بیش از یک start event دارد — پشتیبانی نمی‌شود.');
    }

    public static function noOutgoingFlow(string $elementId): self
    {
        return new self("عنصر '{$elementId}' هیچ خروجی ندارد.");
    }

    public static function deadlock(string $tokenId): self
    {
        return new self("Deadlock تشخیص داده شد در token '{$tokenId}'.");
    }

    public static function processNotPublished(string $key): self
    {
        return new self("فرایند '{$key}' در وضعیت published نیست — instance ایجاد نمی‌شود.");
    }

    public static function instanceNotActive(ProcessInstanceStatus $status): self
    {
        return new self("Instance در وضعیت '{$status->label()}' است و قابل ادامه نیست.");
    }

    public static function serviceTaskNotInWhitelist(string $class): self
    {
        return new self("Service Task '{$class}' در whitelist امن نیست.");
    }

    public static function expressionEvaluationFailed(string $expr, string $reason): self
    {
        return new self("ارزیابی expression '{$expr}' ناموفق بود: {$reason}");
    }

    public static function elementNotFound(string $elementId): self
    {
        return new self("عنصر '{$elementId}' در تعریف فرایند یافت نشد.");
    }

    public static function userTaskNotAssignable(): self
    {
        return new self('این UserTask قابل ارجاع/claim نیست.');
    }

    public static function userTaskAlreadyCompleted(): self
    {
        return new self('این UserTask قبلاً تکمیل شده است.');
    }

    public static function notAuthorizedForUserTask(): self
    {
        return new self('شما مجاز به انجام این UserTask نیستید.');
    }

    public static function variableTypeMismatch(string $name, string $expected, string $actual): self
    {
        return new self("نوع متغیر '{$name}' باید '{$expected}' باشد ولی '{$actual}' است.");
    }

    public static function invalidTransition(string $current, string $new): self
    {
        return new self("تغییر وضعیت از '{$current}' به '{$new}' مجاز نیست.");
    }
}
