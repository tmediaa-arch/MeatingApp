<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Enums;

enum AttendanceRole: string
{
    case Host = 'host';
    case Moderator = 'moderator';
    case Attendee = 'attendee';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Host => 'میزبان',
            self::Moderator => 'ناظر',
            self::Attendee => 'مدعو',
            self::Guest => 'مهمان خارجی',
        };
    }
}
