<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

enum MeetingMode: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'حضوری',
            self::Online => 'آنلاین',
            self::Hybrid => 'ترکیبی',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InPerson => 'heroicon-o-building-office',
            self::Online => 'heroicon-o-video-camera',
            self::Hybrid => 'heroicon-o-rectangle-stack',
        };
    }

    public function requiresRoom(): bool
    {
        return $this !== self::Online;
    }

    public function requiresVideoConference(): bool
    {
        return $this !== self::InPerson;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
