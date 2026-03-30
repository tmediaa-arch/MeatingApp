<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Retrying = 'retrying';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Success => 'موفق',
            self::Failed => 'ناموفق',
            self::Retrying => 'در حال تلاش مجدد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Retrying => 'warning',
        };
    }
}
