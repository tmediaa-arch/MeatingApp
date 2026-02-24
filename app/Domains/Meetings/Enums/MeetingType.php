<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

enum MeetingType: string
{
    case Regular = 'regular';
    case Extraordinary = 'extraordinary';
    case Committee = 'committee';
    case WorkingGroup = 'working_group';
    case Board = 'board';
    case GeneralAssembly = 'general_assembly';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'عادی',
            self::Extraordinary => 'فوق‌العاده',
            self::Committee => 'کمیسیون',
            self::WorkingGroup => 'کارگروه',
            self::Board => 'هیئت‌مدیره',
            self::GeneralAssembly => 'مجمع عمومی',
            self::Other => 'سایر',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
