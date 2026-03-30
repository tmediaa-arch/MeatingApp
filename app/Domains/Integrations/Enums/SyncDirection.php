<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

enum SyncDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Bidirectional = 'bidirectional';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'ورودی (Pull)',
            self::Outbound => 'خروجی (Push)',
            self::Bidirectional => 'دوطرفه',
        };
    }
}
