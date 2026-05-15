<?php

declare(strict_types=1);

namespace App\Domains\Reports\Contracts;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;

/**
 * ReportInterface — قرارداد همه گزارش‌های built-in.
 *
 * AbstractReport این interface را پیاده‌سازی می‌کند؛ گزارش‌های واقعی
 * از AbstractReport ارث می‌برند.
 */
interface ReportInterface
{
    /**
     * نام نمایشی گزارش.
     */
    public function getDisplayName(): string;

    /**
     * توضیح کوتاه گزارش.
     */
    public function getDescription(): string;

    /**
     * schema ورودی‌های گزارش (برای ساخت فرم پارامترها).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getInputSchema(): array;

    /**
     * اجرای گزارش و تولید نتیجه.
     */
    public function run(ReportInput $input, ?User $user = null): ReportResult;

    /**
     * آیا نتیجه گزارش قابل cache است؟
     */
    public function isCacheable(): bool;

    /**
     * مدت اعتبار cache بر حسب دقیقه.
     */
    public function getCacheTtlMinutes(): int;
}
