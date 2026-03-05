<?php
declare(strict_types=1);
namespace App\Domains\Resolutions\Enums;

enum ResolutionType: string
{
    case Decision = 'decision';
    case Directive = 'directive';
    case Recommendation = 'recommendation';
    case PolicyChange = 'policy_change';
    case Budget = 'budget';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Decision => 'تصمیم',
            self::Directive => 'دستورالعمل',
            self::Recommendation => 'توصیه',
            self::PolicyChange => 'تغییر سیاست',
            self::Budget => 'بودجه',
            self::Other => 'سایر',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
