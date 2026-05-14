<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProcessDefinitionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Deprecated = 'deprecated';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Published => 'منتشر شده',
            self::Deprecated => 'منسوخ',
            self::Archived => 'بایگانی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Deprecated => 'warning',
            self::Archived => 'danger',
        };
    }

    public function canStartNewInstance(): bool
    {
        return $this === self::Published;
    }

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft => in_array($new, [self::Published, self::Archived], true),
            self::Published => in_array($new, [self::Deprecated, self::Archived], true),
            self::Deprecated => in_array($new, [self::Archived], true),
            self::Archived => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }
}
