<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\VideoConference\Actions\CheckProviderHealthAction;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use Illuminate\Console\Command;

class VideoConferenceHealthCheck extends Command
{
    protected $signature = 'vc:health-check {--provider= : id خاص}';

    protected $description = 'بررسی سلامت providerهای ویدئوکنفرانس و به‌روزرسانی health_status';

    public function handle(CheckProviderHealthAction $action): int
    {
        $query = VideoConferenceProvider::query()->where('is_active', true);

        if ($id = $this->option('provider')) {
            $query->where('id', $id);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->warn('هیچ provider فعالی یافت نشد.');
            return self::SUCCESS;
        }

        $this->info("بررسی {$providers->count()} provider...");

        $bar = $this->output->createProgressBar($providers->count());
        $bar->start();

        foreach ($providers as $provider) {
            try {
                $action->execute($provider);
            } catch (\Throwable $e) {
                $this->error("\n خطا در {$provider->name}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // خلاصه
        $unhealthy = VideoConferenceProvider::active()
            ->where('health_status', 'unhealthy')
            ->count();

        if ($unhealthy > 0) {
            $this->warn("⚠️ {$unhealthy} provider در وضعیت Unhealthy.");
            return self::FAILURE;
        }

        $this->info('✅ همه providerها سالم هستند.');
        return self::SUCCESS;
    }
}
