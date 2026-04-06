<?php

declare(strict_types=1);

namespace App\Domains\Reports\Services;

use App\Domains\Reports\Contracts\ReportInterface;
use App\Domains\Reports\Enums\ReportCategory;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\Report;

/**
 * ReportRegistryService — رجیستری مرکزی گزارش‌ها.
 *
 * این سرویس امکان می‌دهد گزارش‌ها به صورت code-first تعریف شوند:
 *   $registry->register(MyReport::class, ReportCategory::Tasks, key: 'tasks.overdue', name: '...')
 *
 * در boot، رجیستری به DB sync می‌شود (upsert).
 * این روش بهتر از seeder است چون گزارش‌های جدید بدون نیاز به re-seed اضافه می‌شوند.
 */
class ReportRegistryService
{
    /**
     * @var array<int, array{handler:class-string, key:string, name:string, category:ReportCategory, description?:string, formats:array<int,ReportFormat>, cacheable:bool}>
     */
    private array $registry = [];

    public function register(
        string $handlerClass,
        string $key,
        string $displayName,
        ReportCategory $category,
        ?string $description = null,
        array $supportedFormats = [],
        bool $cacheable = true,
        int $cacheTtlMinutes = 60,
    ): void {
        if (!is_subclass_of($handlerClass, ReportInterface::class)) {
            throw new \LogicException(
                "Report handler '{$handlerClass}' باید ReportInterface را پیاده‌سازی کند."
            );
        }

        $this->registry[] = [
            'handler' => $handlerClass,
            'key' => $key,
            'name' => $displayName,
            'category' => $category,
            'description' => $description,
            'formats' => $supportedFormats ?: [ReportFormat::Html, ReportFormat::Pdf, ReportFormat::Xlsx],
            'cacheable' => $cacheable,
            'cache_ttl_minutes' => $cacheTtlMinutes,
        ];
    }

    public function all(): array
    {
        return $this->registry;
    }

    /**
     * Sync همه گزارش‌های ثبت‌شده به DB (idempotent).
     */
    public function syncToDatabase(): int
    {
        $count = 0;

        foreach ($this->registry as $entry) {
            /** @var ReportInterface $handler */
            $handler = app($entry['handler']);

            $formats = array_map(fn (ReportFormat $f) => $f->value, $entry['formats']);

            Report::updateOrCreate(
                ['organization_id' => null, 'key' => $entry['key']],
                [
                    'display_name' => $entry['name'],
                    'description' => $entry['description'] ?? $handler->getDescription(),
                    'category' => $entry['category'],
                    'handler_class' => $entry['handler'],
                    'input_schema' => $handler->getInputSchema(),
                    'supported_formats' => $formats,
                    'is_cacheable' => $entry['cacheable'] && $handler->isCacheable(),
                    'cache_ttl_minutes' => $entry['cache_ttl_minutes'] ?: $handler->getCacheTtlMinutes(),
                    'is_active' => true,
                    'is_system' => true,
                ],
            );

            $count++;
        }

        return $count;
    }
}
