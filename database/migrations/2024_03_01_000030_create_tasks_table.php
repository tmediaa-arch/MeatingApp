<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            // شماره وظیفه: ${org_code}-TSK-${year}-####
            $table->string('task_number', 50)->unique();

            // منبع — می‌تواند از یک مصوبه، یک جلسه، یا standalone باشد
            $table->foreignId('resolution_id')->nullable()
                ->constrained('resolutions')->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()
                ->constrained('meetings')->nullOnDelete();
            // یا یک parent_task برای زیر-وظایف
            $table->foreignId('parent_task_id')->nullable()
                ->constrained('tasks')->nullOnDelete();

            $table->string('title', 500);
            $table->longText('description')->nullable();

            // نوع وظیفه
            $table->string('type', 30)->default('action');
            // action, document, decision, meeting, review, approval, other

            // اولویت
            $table->string('priority', 20)->default('normal'); // critical, high, normal, low

            // وضعیت وظیفه - state machine
            // open → assigned → in_progress → submitted → under_review →
            //   completed | needs_revision (→ in_progress) | cancelled
            $table->string('status', 30)->default('open')->index();

            // ارجاع
            $table->foreignId('assignee_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_org_unit_id')->nullable()
                ->constrained('org_units')->nullOnDelete();

            // ناظر / تأییدکننده
            $table->foreignId('supervisor_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('approver_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            // تاریخ‌ها
            $table->timestamp('assigned_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // SLA
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->unsignedInteger('actual_hours')->nullable();

            // درصد پیشرفت
            $table->unsignedTinyInteger('progress_percent')->default(0);

            // escalation
            $table->boolean('is_overdue')->default(false);
            $table->unsignedInteger('escalation_level')->default(0); // 0, 1, 2, 3
            $table->timestamp('last_escalated_at')->nullable();

            // نتیجه
            $table->longText('result_summary')->nullable();
            $table->string('completion_quality', 20)->nullable(); // excellent, good, acceptable, poor

            // متادیتا
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            // محرمانگی
            $table->string('confidentiality_level', 20)->default('internal')->index();

            // creator
            $table->foreignId('creator_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌های پیشرفته برای queries پربازده
            $table->index(['organization_id', 'status']);
            $table->index(['assignee_employee_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('priority');
            $table->index('is_overdue');
        });

        // درخواست‌های تمدید مهلت — append-only
        Schema::create('task_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users');

            // تاریخ‌های جدید
            $table->date('original_due_date');
            $table->date('requested_due_date');

            $table->text('reason');

            // وضعیت: pending, approved, rejected
            $table->string('status', 20)->default('pending');

            // پاسخ
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'status']);
        });

        // به‌روزرسانی‌های پیشرفت — append-only
        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('updater_user_id')->constrained('users');

            // نوع: comment, status_change, progress_update, attachment, escalation
            $table->string('update_type', 30);

            $table->text('content')->nullable();

            // اگر status تغییر کرده
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            // اگر progress تغییر کرده
            $table->unsignedTinyInteger('old_progress')->nullable();
            $table->unsignedTinyInteger('new_progress')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['task_id', 'occurred_at']);
            $table->index('update_type');
        });

        // پیوست‌های وظیفه
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('file_path', 500);
            $table->string('file_name', 300);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_hash', 128)->nullable();

            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('task_extensions');
        Schema::dropIfExists('tasks');
    }
};
