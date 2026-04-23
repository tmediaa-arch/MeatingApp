<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Dashboards\Services\DashboardRegistryService;
use App\Domains\Reports\Services\ReportRegistryService;
use Illuminate\Database\Seeder;

/**
 * Phase 6 — Seed system reports و default dashboards از طریق registry.
 *
 * این Seeder خودش گزارش/داشبورد را تعریف نمی‌کند؛ تعاریف در
 * DomainServiceProvider هستند. این Seeder صرفاً registry هایی که در
 * boot ساخته شده‌اند را به DB sync می‌کند.
 *
 * Idempotent: می‌تواند چندین بار اجرا شود.
 */
class Phase6RegistrySeeder extends Seeder
{
    public function run(): void
    {
        /** @var ReportRegistryService $reports */
        $reports = app(ReportRegistryService::class);
        $reportCount = $reports->syncToDatabase();
        $this->command->info("Phase 6: {$reportCount} گزارش sync شد.");

        /** @var DashboardRegistryService $dashboards */
        $dashboards = app(DashboardRegistryService::class);
        $dashboardCount = $dashboards->syncToDatabase();
        $this->command->info("Phase 6: {$dashboardCount} داشبورد sync شد.");
    }
}
