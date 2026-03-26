<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Services;

use App\Domains\VideoConference\Adapters\AlocomVideoConferenceProvider;
use App\Domains\VideoConference\Adapters\BigBlueButtonProvider;
use App\Domains\VideoConference\Adapters\JitsiVideoConferenceProvider;
use App\Domains\VideoConference\Adapters\NullVideoConferenceProvider;
use App\Domains\VideoConference\Contracts\VideoConferenceProviderInterface;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use Illuminate\Support\Facades\Crypt;

/**
 * مدیر provider — یک singleton که:
 *  1. نام driver را به کلاس adapter متناظر map می‌کند
 *  2. تنظیمات provider از DB را می‌خواند (decrypt کرده) و در adapter ست می‌کند
 *  3. instance ready-to-use برمی‌گرداند
 *
 * تمام درخواست‌های بیرونی از این manager عبور می‌کنند — هیچ کلاسی
 * مستقیماً adapter ها را instantiate نمی‌کند.
 */
class VideoConferenceProviderManager
{
    /**
     * Map از driver enum به کلاس adapter.
     */
    private array $driverMap = [
        VideoConferenceDriver::Alocom->value => AlocomVideoConferenceProvider::class,
        VideoConferenceDriver::Jitsi->value => JitsiVideoConferenceProvider::class,
        VideoConferenceDriver::BigBlueButton->value => BigBlueButtonProvider::class,
        VideoConferenceDriver::Null->value => NullVideoConferenceProvider::class,
    ];

    /**
     * cache adapterهای configured شده — تا برای هر provider در یک request
     * چندبار configure نشود.
     *
     * @var array<int, VideoConferenceProviderInterface>
     */
    private array $resolved = [];

    /**
     * Resolve یک adapter از روی Provider model.
     */
    public function resolve(VideoConferenceProvider $provider): VideoConferenceProviderInterface
    {
        if (isset($this->resolved[$provider->id])) {
            return $this->resolved[$provider->id];
        }

        $driverKey = $provider->driver instanceof VideoConferenceDriver
            ? $provider->driver->value
            : (string) $provider->driver;

        $adapter = $this->makeAdapterForDriver($driverKey);

        // decrypt config و آن را به adapter پاس می‌دهیم
        $config = $this->decryptConfig($provider->config_encrypted ?? '');
        $adapter->configure($config);

        $this->resolved[$provider->id] = $adapter;

        return $adapter;
    }

    /**
     * Resolve از روی driver key (بدون نیاز به DB) — برای health-check و test.
     */
    public function makeAdapterForDriver(string $driver): VideoConferenceProviderInterface
    {
        if (!isset($this->driverMap[$driver])) {
            throw VideoConferenceException::driverNotSupported($driver);
        }

        return app($this->driverMap[$driver]);
    }

    /**
     * فهرست تمام driverهای پشتیبانی‌شده.
     */
    public function supportedDrivers(): array
    {
        return array_keys($this->driverMap);
    }

    /**
     * مدیر می‌تواند یک Provider جدید (driver custom) را در run-time register کند.
     */
    public function registerDriver(string $driver, string $adapterClass): void
    {
        if (!is_subclass_of($adapterClass, VideoConferenceProviderInterface::class)) {
            throw new \InvalidArgumentException(
                "{$adapterClass} باید VideoConferenceProviderInterface را پیاده‌سازی کند.",
            );
        }
        $this->driverMap[$driver] = $adapterClass;
    }

    /**
     * یک Provider پیش‌فرض برای organization را پیدا می‌کند.
     */
    public function findDefaultForOrganization(int $organizationId): VideoConferenceProvider
    {
        $provider = VideoConferenceProvider::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (!$provider) {
            // fallback: هر provider فعال
            $provider = VideoConferenceProvider::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->first();
        }

        if (!$provider) {
            throw VideoConferenceException::providerNotFound();
        }

        return $provider;
    }

    /**
     * decrypt config — هر provider config خود را encrypted نگه می‌دارد.
     */
    private function decryptConfig(string $encrypted): array
    {
        if ($encrypted === '') return [];

        try {
            $decrypted = Crypt::decryptString($encrypted);
            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable $e) {
            // اگر encrypt نشده باشد (مثلاً در seed/test)، مستقیم json باشد
            return json_decode($encrypted, true) ?? [];
        }
    }

    /**
     * Helper برای encrypt کردن config در زمان save.
     */
    public function encryptConfig(array $config): string
    {
        return Crypt::encryptString(json_encode($config, JSON_UNESCAPED_UNICODE));
    }
}
