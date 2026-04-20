<?php

declare(strict_types=1);

namespace App\Domains\Calendar\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Value Object برای یک بازه زمانی.
 *
 * نکات:
 * - immutable است
 * - end همیشه بعد از start
 * - متد های منطقی روی بازه‌ها (intersects, contains, ...)
 */
final readonly class TimeRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($this->end <= $this->start) {
            throw new \InvalidArgumentException(
                "TimeRange end ({$this->end->toIso8601String()}) must be after start ({$this->start->toIso8601String()})"
            );
        }
    }

    public static function from(DateTimeInterface $start, DateTimeInterface $end): self
    {
        return new self(
            CarbonImmutable::instance($start),
            CarbonImmutable::instance($end),
        );
    }

    public static function fromMinutes(DateTimeInterface $start, int $minutes): self
    {
        $s = CarbonImmutable::instance($start);
        return new self($s, $s->addMinutes($minutes));
    }

    public function durationInMinutes(): int
    {
        return $this->start->diffInMinutes($this->end);
    }

    public function durationInSeconds(): int
    {
        return $this->start->diffInSeconds($this->end);
    }

    /**
     * آیا این بازه با بازه دیگری تلاقی دارد؟
     * فرمول: a.start < b.end AND b.start < a.end
     */
    public function intersects(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * آیا این بازه به‌طور کامل بازه دیگر را در بر می‌گیرد؟
     */
    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $this->end >= $other->end;
    }

    public function containsMoment(DateTimeInterface $moment): bool
    {
        $m = CarbonImmutable::instance($moment);
        return $m >= $this->start && $m <= $this->end;
    }

    public function expand(int $minutesBefore, int $minutesAfter): self
    {
        return new self(
            $this->start->subMinutes($minutesBefore),
            $this->end->addMinutes($minutesAfter),
        );
    }

    public function shrink(int $minutesFromStart, int $minutesFromEnd): self
    {
        return new self(
            $this->start->addMinutes($minutesFromStart),
            $this->end->subMinutes($minutesFromEnd),
        );
    }

    public function isInPast(): bool
    {
        return $this->end < now();
    }

    public function isInFuture(): bool
    {
        return $this->start > now();
    }

    public function isCurrent(): bool
    {
        return $this->containsMoment(now());
    }

    /**
     * آیا این بازه کاملاً داخل یک روز است؟
     */
    public function isSameDay(): bool
    {
        return $this->start->isSameDay($this->end);
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'duration_minutes' => $this->durationInMinutes(),
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            '[%s → %s]',
            $this->start->format('Y-m-d H:i'),
            $this->end->format('Y-m-d H:i'),
        );
    }
}
