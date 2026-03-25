<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Adapters;

use App\Domains\VideoConference\Contracts\VideoConferenceProviderInterface;
use App\Domains\VideoConference\DTOs\RecordingInfo;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;

/**
 * پایه مشترک برای adapterها — شامل validation پیکربندی
 * و پیاده‌سازی پیش‌فرض methodهایی که عمومی هستند.
 */
abstract class AbstractVideoConferenceProvider implements VideoConferenceProviderInterface
{
    protected array $config = [];
    protected bool $isConfigured = false;

    public function configure(array $config): void
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->isConfigured = true;
    }

    /**
     * Hook برای adapterها تا config خود را validate کنند.
     * پیش‌فرض: validation معمولی (نیاز نیست override شود اگر config ساده است).
     */
    protected function validateConfig(array $config): void
    {
        $required = $this->requiredConfigKeys();
        foreach ($required as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new \InvalidArgumentException(
                    sprintf('پیکربندی Provider ناقص: کلید "%s" الزامی است.', $key),
                );
            }
        }
    }

    /**
     * فهرست کلیدهای الزامی config — هر adapter override می‌کند.
     *
     * @return string[]
     */
    abstract protected function requiredConfigKeys(): array;

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured) {
            throw VideoConferenceException::externalApiFailed(
                'Provider قبل از استفاده باید configure شود.',
            );
        }
    }

    /**
     * پیاده‌سازی پیش‌فرض برای ضبط — بسیاری از providerها ضبط ندارند.
     */
    public function supportsRecording(): bool
    {
        return false;
    }

    public function supportsBreakoutRooms(): bool
    {
        return false;
    }

    public function startRecording(string $externalRoomId): void
    {
        if (!$this->supportsRecording()) {
            throw VideoConferenceException::recordingNotSupported($this->driverKey());
        }
        $this->doStartRecording($externalRoomId);
    }

    public function stopRecording(string $externalRoomId): void
    {
        if (!$this->supportsRecording()) {
            throw VideoConferenceException::recordingNotSupported($this->driverKey());
        }
        $this->doStopRecording($externalRoomId);
    }

    public function getRecording(string $externalRoomId): RecordingInfo
    {
        if (!$this->supportsRecording()) {
            return new RecordingInfo(status: 'not_supported');
        }
        return $this->doGetRecording($externalRoomId);
    }

    // ── method‌هایی که adapterهای supportsRecording=true باید override کنند ──

    protected function doStartRecording(string $externalRoomId): void
    {
        throw VideoConferenceException::recordingNotSupported($this->driverKey());
    }

    protected function doStopRecording(string $externalRoomId): void
    {
        throw VideoConferenceException::recordingNotSupported($this->driverKey());
    }

    protected function doGetRecording(string $externalRoomId): RecordingInfo
    {
        return new RecordingInfo(status: 'not_supported');
    }
}
