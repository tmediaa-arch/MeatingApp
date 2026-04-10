<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Exports tracking + API personal access tokens metadata
 *
 * - export_jobs: ردیابی همه export های سامانه (Excel، PDF، CSV، ICS)
 *   چون export ها می‌توانند سنگین باشند، asynchronous با Job queue اجرا می‌شوند
 * - api_personal_tokens: meta extra برای Sanctum tokens (rate limit، scope ها، org_id)
 *
 * توجه: جدول personal_access_tokens خود Sanctum توسط مهاجرت Sanctum ساخته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')
                ->constrained('users')->cascadeOnDelete();

            // export چی؟
            $table->string('export_type', 50); // meetings, minutes, resolutions, tasks, calendar_ics, custom_report, ...
            $table->string('format', 20); // xlsx, csv, pdf, ics, json

            // پارامترها
            $table->json('input_params')->nullable();
            $table->string('label', 300)->nullable();

            // وضعیت
            $table->string('status', 20)->default('queued'); // queued, processing, completed, failed, expired
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('row_count')->nullable();

            // خروجی
            $table->foreignId('output_file_id')->nullable()
                ->constrained('files')->nullOnDelete();

            // اعتبار خروجی
            $table->timestamp('expires_at')->nullable()->index();

            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'requested_by_user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['export_type', 'format']);
        });

        // متادیتای اضافی برای Sanctum tokens
        Schema::create('api_token_metadata', function (Blueprint $table) {
            $table->id();
            // FK به personal_access_tokens (Sanctum) — fk lazy because Sanctum table comes later
            $table->unsignedBigInteger('token_id');
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('description', 500)->nullable();

            // rate limiting
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->unsignedInteger('rate_limit_per_day')->default(10000);

            // IP whitelist (CIDR)
            $table->json('allowed_ips')->nullable();

            // محدودیت زمانی
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();

            // آمار
            $table->unsignedBigInteger('total_requests')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('token_id');
            $table->index(['organization_id', 'revoked_at']);
        });

        // calendar feed tokens — برای ICS subscription URL (نیازی به Auth ندارد چون token در URL است)
        Schema::create('calendar_feed_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('token', 100)->unique(); // random token در URL استفاده می‌شود
            $table->string('name', 200);

            // فیلتر چه چیزی در feed می‌آید
            $table->json('filter_config')->nullable();
            // [event_types: [meeting, deadline], confidentiality_max: 'internal']

            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_tokens');
        Schema::dropIfExists('api_token_metadata');
        Schema::dropIfExists('export_jobs');
    }
};
