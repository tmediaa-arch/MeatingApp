<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول دامنه ServiceRequests:
 *  - service_requests: درخواست‌های جانبی (نقلیه، پذیرایی، تجهیزات، پشتیبانی)
 *  - service_request_updates: تاریخچه (append-only)
 *  - service_request_attachments: پیوست‌ها
 *
 * این درخواست‌ها می‌توانند به یک Meeting یا مستقل باشند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            $table->string('request_number', 50)->unique()
                ->comment('شماره خودکار: {ORG}-SRV-{YYYY}-####');

            // نوع درخواست
            $table->enum('type', [
                'transport',     // نقلیه
                'catering',      // پذیرایی
                'equipment',     // تجهیزات
                'support',       // پشتیبانی فنی
                'venue_setup',   // چیدمان سالن
                'other',
            ]);

            $table->string('title');
            $table->text('description')->nullable();

            // ارتباط با Meeting (اختیاری)
            $table->foreignId('meeting_id')->nullable()
                ->constrained('meetings')->nullOnDelete();

            // فیلدهای اختصاصی هر نوع — در JSON
            $table->json('type_specific_data')->nullable()
                ->comment('داده‌های اختصاصی هر نوع — مبدأ/مقصد، تعداد نفرات، ...');

            // اولویت و وضعیت
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->enum('status', [
                'draft',         // پیش‌نویس
                'submitted',     // ارسال شده برای تأیید
                'under_review',  // در حال بررسی
                'approved',      // تأیید شده
                'rejected',      // رد شده
                'in_progress',   // در حال انجام
                'completed',     // انجام شده
                'cancelled',     // لغو شد
            ])->default('draft');

            // زمان‌بندی
            $table->timestamp('required_at')
                ->comment('زمان مورد نیاز ارائه خدمت');
            $table->integer('estimated_duration_minutes')->nullable();

            // درخواست‌کننده
            $table->foreignId('requester_user_id')->constrained('users');
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees');
            $table->foreignId('requester_unit_id')->nullable()->constrained('org_units');

            // واحد ارائه‌دهنده
            $table->foreignId('provider_unit_id')->nullable()
                ->constrained('org_units')
                ->comment('واحد سازمانی مسئول ارائه خدمت');
            $table->foreignId('assigned_to_employee_id')->nullable()
                ->constrained('employees')
                ->comment('کارمند مسئول این درخواست');

            // بررسی و تأیید
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();

            // ارقام
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();

            // برچسب
            $table->json('tags')->nullable();

            $table->timestamps();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();

            $table->index(['organization_id', 'status', 'required_at']);
            $table->index(['type', 'status']);
            $table->index(['provider_unit_id', 'status']);
            $table->index(['requester_user_id', 'status']);
            $table->index('required_at');
        });

        // ─────────────────────────────────────────────────
        // به‌روزرسانی‌ها — append-only
        // ─────────────────────────────────────────────────
        Schema::create('service_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')
                ->constrained('service_requests')
                ->cascadeOnDelete();

            $table->enum('update_type', [
                'status_change',
                'comment',
                'assignment_change',
                'cost_update',
                'schedule_change',
                'attachment_added',
            ]);

            $table->string('from_value', 100)->nullable();
            $table->string('to_value', 100)->nullable();
            $table->text('comment')->nullable();
            $table->json('payload')->nullable();

            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_request_id', 'occurred_at']);
        });

        // ─────────────────────────────────────────────────
        // پیوست‌ها
        // ─────────────────────────────────────────────────
        Schema::create('service_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')
                ->constrained('service_requests')
                ->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();

            $table->string('purpose', 50)->nullable()
                ->comment('quote, invoice, evidence, ...');
            $table->text('description')->nullable();

            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_attachments');
        Schema::dropIfExists('service_request_updates');
        Schema::dropIfExists('service_requests');
    }
};
