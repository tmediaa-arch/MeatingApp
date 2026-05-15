<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

/**
 * SyncResult — جمع‌بندی نتیجه یک عملیات sync (ایجاد/به‌روزرسانی/رد/خطا).
 */
final class SyncResult
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $failed = 0;

    /** @var array<string, string> key => پیام خطا */
    public array $errors = [];

    public function incrementCreated(): void
    {
        $this->created++;
    }

    public function incrementUpdated(): void
    {
        $this->updated++;
    }

    public function incrementSkipped(?string $reason = null): void
    {
        $this->skipped++;
    }

    public function recordError(string $key, string $message): void
    {
        $this->failed++;
        $this->errors[$key] = $message;
    }

    public function totalProcessed(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failed;
    }

    public function hasErrors(): bool
    {
        return $this->failed > 0;
    }
}
