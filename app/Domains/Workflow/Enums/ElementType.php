<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Enums;

/**
 * انواع عناصر BPMN 2.0 که موتور ما پشتیبانی می‌کند.
 *
 * این لیست محدودیت‌گذاری شده — اگر BPMN XML عنصری خارج از این
 * لیست داشته باشد، در زمان پارس rejected می‌شود.
 */
enum ElementType: string
{
    // ── Events ──
    case StartEvent = 'startEvent';
    case EndEvent = 'endEvent';
    case IntermediateCatchEvent = 'intermediateCatchEvent';
    case IntermediateThrowEvent = 'intermediateThrowEvent';
    case BoundaryEvent = 'boundaryEvent';

    // ── Tasks ──
    case UserTask = 'userTask';
    case ServiceTask = 'serviceTask';
    case ManualTask = 'manualTask';
    case ScriptTask = 'scriptTask';
    case SendTask = 'sendTask';
    case ReceiveTask = 'receiveTask';

    // ── Gateways ──
    case ExclusiveGateway = 'exclusiveGateway';
    case ParallelGateway = 'parallelGateway';
    case InclusiveGateway = 'inclusiveGateway';
    case EventBasedGateway = 'eventBasedGateway';

    // ── Sub-Process ──
    case SubProcess = 'subProcess';
    case CallActivity = 'callActivity';

    // ── Flow ──
    case SequenceFlow = 'sequenceFlow';

    public function label(): string
    {
        return match ($this) {
            self::StartEvent => 'رویداد شروع',
            self::EndEvent => 'رویداد پایان',
            self::IntermediateCatchEvent => 'رویداد میانی',
            self::IntermediateThrowEvent => 'رویداد throw',
            self::BoundaryEvent => 'رویداد مرزی',
            self::UserTask => 'وظیفه کاربر',
            self::ServiceTask => 'وظیفه سرویس',
            self::ManualTask => 'وظیفه دستی',
            self::ScriptTask => 'وظیفه اسکریپت',
            self::SendTask => 'وظیفه ارسال',
            self::ReceiveTask => 'وظیفه دریافت',
            self::ExclusiveGateway => 'گیت انحصاری',
            self::ParallelGateway => 'گیت موازی',
            self::InclusiveGateway => 'گیت اختیاری',
            self::EventBasedGateway => 'گیت رویداد محور',
            self::SubProcess => 'زیر-فرایند',
            self::CallActivity => 'فراخوانی فرایند',
            self::SequenceFlow => 'جریان',
        };
    }

    public function isEvent(): bool
    {
        return in_array($this, [
            self::StartEvent,
            self::EndEvent,
            self::IntermediateCatchEvent,
            self::IntermediateThrowEvent,
            self::BoundaryEvent,
        ], true);
    }

    public function isTask(): bool
    {
        return in_array($this, [
            self::UserTask,
            self::ServiceTask,
            self::ManualTask,
            self::ScriptTask,
            self::SendTask,
            self::ReceiveTask,
        ], true);
    }

    public function isGateway(): bool
    {
        return in_array($this, [
            self::ExclusiveGateway,
            self::ParallelGateway,
            self::InclusiveGateway,
            self::EventBasedGateway,
        ], true);
    }

    public function isFlow(): bool
    {
        return $this === self::SequenceFlow;
    }

    public function requiresWait(): bool
    {
        // عناصری که token باید روی آنها بنشیند و منتظر بماند
        return in_array($this, [
            self::UserTask,
            self::ReceiveTask,
            self::IntermediateCatchEvent,
        ], true);
    }
}
