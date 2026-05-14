<?php
declare(strict_types=1);
namespace App\Domains\Tasks\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskType: string implements HasLabel
{
    case Action = 'action';
    case Document = 'document';
    case Decision = 'decision';
    case Meeting = 'meeting';
    case Review = 'review';
    case Approval = 'approval';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Action => 'اقدام',
            self::Document => 'تهیه مستند',
            self::Decision => 'تصمیم‌گیری',
            self::Meeting => 'برگزاری جلسه',
            self::Review => 'بازبینی',
            self::Approval => 'تأیید',
            self::Other => 'سایر',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
