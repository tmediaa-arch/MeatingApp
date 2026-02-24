<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

enum AttendanceStatus: string
{
    case Unknown = 'unknown';
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case LeftEarly = 'left_early';
    case Partial = 'partial';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'نامعلوم',
            self::Present => 'حاضر',
            self::Absent => 'غایب',
            self::Late => 'تأخیر',
            self::LeftEarly => 'ترک زودهنگام',
            self::Partial => 'حضور جزئی',
            self::Remote => 'حضور آنلاین',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present, self::Remote => 'success',
            self::Late, self::LeftEarly, self::Partial => 'warning',
            self::Absent => 'danger',
            default => 'gray',
        };
    }

    public function countsAsPresent(): bool
    {
        return in_array($this, [
            self::Present,
            self::Late,
            self::LeftEarly,
            self::Partial,
            self::Remote,
        ], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
