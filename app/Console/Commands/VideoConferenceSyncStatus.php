<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\VideoConference\Models\VideoConferenceRoom;
use App\Domains\VideoConference\Services\VideoConferenceService;
use Illuminate\Console\Command;

class VideoConferenceSyncStatus extends Command
{
    protected $signature = 'vc:sync-status {--limit=50 : حداکثر تعداد اتاق در یک run}';

    protected $description = 'Sync وضعیت اتاق‌های فعال با provider — برای تشخیص شروع/پایان جلسات';

    public function handle(VideoConferenceService $vc): int
    {
        $limit = (int) $this->option('limit');

        $rooms = VideoConferenceRoom::query()
            ->active()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rooms->isEmpty()) {
            $this->info('هیچ اتاق فعالی برای sync نیست.');
            return self::SUCCESS;
        }

        $this->info("Sync کردن {$rooms->count()} اتاق...");

        $synced = 0;
        $errors = 0;
        foreach ($rooms as $room) {
            try {
                $vc->syncStatus($room);
                $synced++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error("خطا در sync room {$room->room_uuid}: " . $e->getMessage());
            }
        }

        $this->info("✅ {$synced} اتاق sync شد." . ($errors > 0 ? " ⚠️ {$errors} خطا" : ''));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
