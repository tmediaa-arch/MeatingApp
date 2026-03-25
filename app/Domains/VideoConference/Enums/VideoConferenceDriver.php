<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Enums;

enum VideoConferenceDriver: string
{
    case Alocom = 'alocom';
    case Jitsi = 'jitsi';
    case BigBlueButton = 'bigbluebutton';
    case Null = 'null';

    public function label(): string
    {
        return match ($this) {
            self::Alocom => 'Alocom',
            self::Jitsi => 'Jitsi Meet',
            self::BigBlueButton => 'BigBlueButton',
            self::Null => 'لینک دستی (بدون provider)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Alocom => 'سامانه ویدئوکنفرانس Alocom — سازگار با زیرساخت داخلی',
            self::Jitsi => 'Jitsi Meet — open-source و قابل host داخلی',
            self::BigBlueButton => 'BigBlueButton — مناسب برای جلسات بزرگ و کلاس مجازی',
            self::Null => 'بدون اتصال به provider — صرفاً ثبت لینک جلسه دستی',
        };
    }

    public function supportsRecording(): bool
    {
        return match ($this) {
            self::Alocom, self::BigBlueButton => true,
            self::Jitsi => true, // با Jibri
            self::Null => false,
        };
    }

    public function supportsBreakoutRooms(): bool
    {
        return match ($this) {
            self::BigBlueButton, self::Jitsi => true,
            default => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($d) => [$d->value => $d->label()])
            ->toArray();
    }
}
