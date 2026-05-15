<?php

declare(strict_types=1);

namespace App\Domains\Exports\Contracts;

use App\Domains\Exports\Models\ExportJob;

/**
 * ExportGeneratorInterface — قرارداد همه generator های خروجی (PDF/Excel/CSV/ICS).
 */
interface ExportGeneratorInterface
{
    /**
     * آیا این generator می‌تواند job داده‌شده را پردازش کند؟
     */
    public function supports(ExportJob $job): bool;

    /**
     * تولید خروجی.
     *
     * @return array{content: string, mime: string, extension: string, filename: string}
     */
    public function generate(ExportJob $job): array;
}
