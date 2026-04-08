<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Dashboards domain
 *
 * - dashboards: تعریف داشبورد (هر نقش یک یا چند داشبورد می‌تواند داشته باشد)
 * - dashboard_widgets: ویجت‌های موجود در یک داشبورد
 * - user_dashboard_preferences: تنظیمات شخصی هر کاربر (چینش، فیلتر)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('key', 100); // executive, unit_manager, secretary, room_admin, vc_admin, auditor
            $table->string('display_name', 200);
            $table->text('description')->nullable();

            // نقش‌هایی که می‌توانند این داشبورد را ببینند (به ترتیب نقش spatie)
            $table->json('allowed_roles')->nullable();

            // آیکون و رنگ
            $table->string('icon', 100)->nullable();
            $table->string('color', 30)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();

            $table->string('key', 100); // upcoming_meetings, overdue_tasks, kpi_attendance, ...
            $table->string('display_name', 200);

            // کلاس PHP که این ویجت را Render می‌کند
            $table->string('widget_class', 200);

            // نوع ویجت
            $table->string('type', 30); // stat, chart, table, list, calendar
            $table->string('chart_type', 30)->nullable(); // line, bar, pie, doughnut, area

            // چینش
            $table->unsignedInteger('row')->default(0);
            $table->unsignedInteger('column')->default(0);
            $table->unsignedInteger('width')->default(4); // 1-12 grid system
            $table->unsignedInteger('height')->default(1); // unit-based

            // تنظیمات
            $table->json('config')->nullable(); // فیلترها، رنگ‌ها، threshold
            $table->json('input_params')->nullable();

            // refresh
            $table->unsignedInteger('refresh_interval_seconds')->default(0); // 0 = no auto refresh
            $table->boolean('is_cacheable')->default(true);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dashboard_id', 'is_active']);
        });

        Schema::create('user_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();

            // chosen as default
            $table->boolean('is_pinned')->default(false);

            // override layout
            $table->json('widget_overrides')->nullable();
            // [{ widget_id, hidden, row, column, width, height, custom_config }]

            // فیلترهای دلخواه کاربر
            $table->json('custom_filters')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'dashboard_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboards');
    }
};
