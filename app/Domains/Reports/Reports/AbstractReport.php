<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\Contracts\ReportInterface;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;

/**
 * AbstractReport — کلاس پایه برای همه گزارش‌ها.
 *
 * subclassها فقط لازم است getDisplayName، getDescription، getInputSchema و run
 * را override کنند. cache settings پیش‌فرض هستند.
 */
abstract class AbstractReport implements ReportInterface
{
    abstract public function getDisplayName(): string;
    abstract public function getDescription(): string;
    abstract public function getInputSchema(): array;
    abstract public function run(ReportInput $input, ?User $user = null): ReportResult;

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtlMinutes(): int
    {
        return 60;
    }

    /**
     * Helper برای ساخت تعریف ستون
     */
    protected function column(string $key, string $label, string $type = 'string', array $extra = []): array
    {
        return ReportResult::column($key, $label, $type, $extra);
    }

    /**
     * Helper برای محدوده زمانی پیش‌فرض (ماه جاری)
     */
    protected function defaultDateRange(ReportInput $input): array
    {
        $from = $input->dateFrom ?? now()->startOfMonth()->toImmutable();
        $to = $input->dateTo ?? now()->endOfMonth()->toImmutable();

        return [$from, $to];
    }
}
