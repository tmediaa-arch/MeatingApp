<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ──── Factory namespace resolution ────
        // مدل‌ها در App\Domains\{Domain}\Models\X زندگی می‌کنند ولی Factoryها
        // در Database\Factories\XFactory هستند. این bridge نام را resolve می‌کند.
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $basename = class_basename($modelName);
            return 'Database\\Factories\\' . $basename . 'Factory';
        });

        // ──── Model namespace resolution for factories (reverse) ────
        Factory::guessModelNamesUsing(function (Factory $factory) {
            $factoryClass = get_class($factory);
            $modelName = Str::replaceLast('Factory', '', class_basename($factoryClass));

            // نقشه ساده‌ی reverse — اگر مدل خاصی نیاز به override داشت، اینجا اضافه شود
            $candidates = [
                "App\\Domains\\Identity\\Models\\{$modelName}",
                "App\\Domains\\Organization\\Models\\{$modelName}",
                "App\\Domains\\Meetings\\Models\\{$modelName}",
                "App\\Domains\\Rooms\\Models\\{$modelName}",
                "App\\Domains\\Minutes\\Models\\{$modelName}",
                "App\\Domains\\Resolutions\\Models\\{$modelName}",
                "App\\Domains\\Tasks\\Models\\{$modelName}",
                "App\\Domains\\Notifications\\Models\\{$modelName}",
                "App\\Domains\\Files\\Models\\{$modelName}",
                "App\\Domains\\Workflow\\Models\\{$modelName}",
                "App\\Domains\\VideoConference\\Models\\{$modelName}",
                "App\\Domains\\ServiceRequests\\Models\\{$modelName}",
            ];

            foreach ($candidates as $candidate) {
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }

            return "App\\Models\\{$modelName}"; // fallback
        });
    }

    public function boot(): void
    {
        // طول رشته پیش‌فرض برای MySQL قدیمی‌تر
        Schema::defaultStringLength(191);

        // pagination Bootstrap RTL (اختیاری)
        // \Illuminate\Pagination\Paginator::useBootstrapFive();

        // فقط در محیط production، secure مدل‌ها
        if (app()->environment('production')) {
            Model::preventLazyLoading();
            Model::preventSilentlyDiscardingAttributes();
        }
    }
}
