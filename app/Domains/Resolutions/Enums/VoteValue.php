<?php
declare(strict_types=1);
namespace App\Domains\Resolutions\Enums;

enum VoteValue: string
{
    case For = 'for';
    case Against = 'against';
    case Abstain = 'abstain';

    public function label(): string
    {
        return match ($this) {
            self::For => 'موافق',
            self::Against => 'مخالف',
            self::Abstain => 'ممتنع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::For => 'success',
            self::Against => 'danger',
            self::Abstain => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::For => 'heroicon-o-hand-thumb-up',
            self::Against => 'heroicon-o-hand-thumb-down',
            self::Abstain => 'heroicon-o-minus-circle',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
