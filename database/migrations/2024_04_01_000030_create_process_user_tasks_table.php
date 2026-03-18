<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UserTasks تولیدشده در زمان رسیدن token به یک عنصر userTask.
 *
 * این مفهوم با Tasks دامنه‌ی Phase 3 (وظایف کسب‌وکار) متفاوت است:
 *  - Phase 3 Tasks: وظیفه ناشی از مصوبه — مستقل از موتور فرایند
 *  - Phase 4 UserTasks: گام human در یک BPMN flow — runtime
 *
 * یک UserTask می‌تواند به یک Phase 3 Task هم مرتبط شود
 * (مثلاً اگر service task آن را ایجاد کند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_user_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('process_tokens')->cascadeOnDelete();

            $table->string('element_id', 100)
                ->comment('id عنصر userTask در BPMN');
            $table->string('name');
            $table->text('description')->nullable();

            // assignee — می‌تواند کاربر مستقیم یا candidate باشد
            $table->foreignId('assignee_user_id')->nullable()->constrained('users');
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees');
            $table->json('candidate_user_ids')->nullable()
                ->comment('کاربرانی که می‌توانند claim کنند');
            $table->json('candidate_role_names')->nullable()
                ->comment('نقش‌هایی که اعضای آن می‌توانند claim کنند');

            // وضعیت
            $table->enum('status', [
                'created',     // هنوز assigned نشده، در صف candidate
                'assigned',    // assignee مشخص است
                'claimed',     // assignee قبول کرد (در حال انجام)
                'completed',   // تکمیل شد
                'cancelled',   // لغو شد
                'reassigned',  // به فرد دیگری منتقل شد
            ])->default('created');

            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');

            // مهلت
            $table->timestamp('due_at')->nullable();
            $table->timestamp('follow_up_at')->nullable();

            // form
            $table->json('form_schema')->nullable();
            $table->json('form_data')->nullable()
                ->comment('داده‌های فرم پس از تکمیل');

            // تصمیم خروجی (برای gatewayهای بعدی)
            $table->string('outcome', 50)->nullable()
                ->comment('approve / reject / forward / ...');
            $table->text('outcome_comment')->nullable();

            // ارجاع به Phase 3 Task (اختیاری)
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();

            // timestamps
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['instance_id', 'status']);
            $table->index(['assignee_user_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        // ─────────────────────────────────────────────────
        // Incidents — خطاهای runtime نیازمند مداخله انسانی
        // ─────────────────────────────────────────────────
        Schema::create('process_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('process_tokens');

            $table->string('incident_type', 50)
                ->comment('service_task_failed / expression_error / timeout / unauthorized / ...');
            $table->string('element_id', 100)->nullable();

            $table->text('message');
            $table->longText('stack_trace')->nullable();
            $table->json('context')->nullable();

            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open');

            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->index(['instance_id', 'status']);
            $table->index(['incident_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_incidents');
        Schema::dropIfExists('process_user_tasks');
    }
};
