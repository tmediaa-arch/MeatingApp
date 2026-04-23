<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Organization\Models\Organization;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use Illuminate\Database\Seeder;

/**
 * یک Provider پیش‌فرض از نوع Null برای هر سازمان تنظیم می‌کند.
 * این امکان را می‌دهد سازمان بدون نیاز به سرویس خارجی، لینک‌های جلسه دستی را ثبت کند.
 */
class VideoConferenceProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(VideoConferenceProviderManager::class);

        Organization::query()->each(function (Organization $org) use ($manager) {
            $exists = VideoConferenceProvider::where('organization_id', $org->id)->exists();
            if ($exists) {
                $this->command->info("⏭️  Provider برای {$org->name} وجود دارد، رد می‌شود.");
                return;
            }

            VideoConferenceProvider::create([
                'organization_id' => $org->id,
                'name' => 'Null Provider — دستی',
                'driver' => VideoConferenceDriver::Null,
                'config_encrypted' => $manager->encryptConfig([]),
                'supports_recording' => false,
                'supports_streaming' => false,
                'supports_breakout_rooms' => false,
                'is_active' => true,
                'is_default' => true,
                'health_status' => HealthStatus::Healthy,
                'last_health_check_at' => now(),
            ]);

            $this->command->info("✅ Provider پیش‌فرض برای {$org->name} ایجاد شد");
        });
    }
}
