<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Reports domain
 *
 * این مهاجرت سه جدول می‌سازد:
 * - reports: تعریف هر گزارش (متادیتای استاتیک هر گزارش از قبل تعریف‌شده)
 * - report_runs: هر بار اجرای یک گزارش، خروجی ذخیره می‌شود (caching + audit)
 * - report_schedules: زمان‌بندی خودکار برای اجرا و ارسال گزارش
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            // کلید یکتای گزارش: meetings.summary, tasks.overdue, resolutions.execution_rate, ...
            $table->string('key', 100);
            $table->string('display_name', 200);
            $table->text('description')->nullable();

            // دسته‌بندی: meetings, minutes, resolutions, tasks, attendance, files, audit, kpi
            $table->string('category', 50)->index();

            // کلاس PHP که این گزارش را تولید می‌کند (implements ReportInterface)
            $table->string('handler_class', 200);

            // پارامترهای ورودی گزارش (JSON Schema)
            $table->json('input_schema')->nullable();

            // فرمت‌های خروجی پشتیبانی‌شده
            $table->json('supported_formats')->nullable(); // ['html', 'pdf', 'xlsx', 'csv']

            // محرمانگی پیش‌فرض
            $table->string('confidentiality_level', 20)->default('internal');

            // آیا قابل cache است و TTL آن (در دقیقه)
            $table->boolean('is_cacheable')->default(true);
            $table->unsignedInteger('cache_ttl_minutes')->default(60);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // سیستمی یا تعریف‌شده توسط کاربر؟

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'key']);
            $table->index(['category', 'is_active']);
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // پارامترهای اجرا
            $table->json('input_params')->nullable();
            // hash پارامترها برای cache lookup
            $table->string('params_hash', 64)->index();

            // وضعیت
            $table->string('status', 20)->default('queued'); // queued, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // خروجی
            $table->json('result_data')->nullable(); // داده‌های گزارش
            $table->unsignedBigInteger('row_count')->nullable();
            $table->foreignId('output_file_id')->nullable()
                ->constrained('files')->nullOnDelete();
            $table->string('output_format', 20)->nullable();

            // خطا
            $table->text('error_message')->nullable();

            // اعتبار cache
            $table->timestamp('cached_until')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'params_hash', 'cached_until']);
            $table->index(['organization_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('name', 200);
            $table->string('cron_expression', 100); // 0 8 * * 1 — هر دوشنبه ۸ صبح
            $table->json('input_params')->nullable();
            $table->string('output_format', 20)->default('pdf');

            // ارسال
            $table->json('recipient_user_ids')->nullable();
            $table->json('recipient_emails')->nullable();
            $table->string('delivery_method', 30)->default('email'); // email, in_app, both

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('reports');
    }
};
