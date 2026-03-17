<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول runtime موتور Workflow:
 *  - process_instances: نمونه‌های در حال اجرا
 *  - process_tokens:    نشانگرها (token) که محل فعلی اجرا را در BPMN مشخص می‌کنند
 *  - process_variables: متغیرهای instance (key-value با versioning)
 *  - process_history:   لاگ تک‌خطی هر transition (append-only)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────
        // نمونه‌های فرایند در حال اجرا
        // ─────────────────────────────────────────────────
        Schema::create('process_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('instance_uuid')->unique()
                ->comment('شناسه یکتای instance — قابل ارجاع از تمام سامانه');

            $table->foreignId('process_definition_id')->constrained('process_definitions');
            $table->string('process_key', 100);
            $table->integer('process_version');

            $table->foreignId('organization_id')->constrained('organizations');

            // business key — معمولاً ارجاع به entity دامنه‌ای
            $table->string('business_key', 200)->nullable()
                ->comment('کلید کسب‌وکار — مثلاً MIN-2024-0042');

            // polymorphic — instance ممکن است متعلق به entity خاصی باشد
            $table->nullableMorphs('subject');

            // وضعیت
            $table->enum('status', [
                'pending',     // ایجاد شده، هنوز شروع نشده
                'running',     // در حال اجرا
                'suspended',   // متوقف شده (توسط ادمین)
                'completed',   // به پایان رسید
                'cancelled',   // لغو شد
                'failed',      // با خطا متوقف شد
            ])->default('pending');

            // ابتدا و انتها
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('end_reason')->nullable();
            $table->text('failure_reason')->nullable();

            // priority برای ترتیب اجرا (در صف)
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');

            // SLA
            $table->timestamp('sla_due_at')->nullable()
                ->comment('مهلت کلی instance بر اساس فرایند');

            $table->foreignId('starter_user_id')->nullable()->constrained('users');
            $table->json('start_variables')->nullable()
                ->comment('snapshot متغیرها در زمان شروع');
            $table->json('context')->nullable()
                ->comment('context عمومی — IP، session، ...');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_key', 'status']);
            $table->index(['organization_id', 'status', 'started_at']);
            $table->index(['status', 'sla_due_at']);
            $table->index('business_key');
        });

        // ─────────────────────────────────────────────────
        // Tokens — نشانگرهای فعلی در BPMN
        // هر instance می‌تواند چندین token همزمان داشته باشد
        // (Parallel Gateway → split → چند token)
        // ─────────────────────────────────────────────────
        Schema::create('process_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_uuid')->unique();

            $table->foreignId('instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('parent_token_id')->nullable()
                ->constrained('process_tokens')
                ->comment('برای split در Gateway: parent token تولیدکننده');

            // محل فعلی
            $table->string('current_element_id', 100)
                ->comment('id عنصر فعلی در BPMN');
            $table->string('current_element_type', 80);

            // وضعیت token
            $table->enum('status', [
                'active',       // در حال اجرا روی این element
                'waiting',      // منتظر event/timer/user-task
                'consumed',     // در یک Join Gateway مصرف شد
                'cancelled',    // لغو شد
                'completed',    // به end event رسید
            ])->default('active');

            // برای wait
            $table->timestamp('wait_until')->nullable()
                ->comment('برای Timer Events');
            $table->string('wait_for_message', 100)->nullable()
                ->comment('برای Message Events');
            $table->json('wait_condition')->nullable();

            // مسیر طی شده — array of element_id
            $table->json('execution_path')->nullable();

            $table->timestamp('entered_current_element_at')->nullable();
            $table->timestamp('exited_at')->nullable();

            $table->timestamps();

            $table->index(['instance_id', 'status']);
            $table->index(['status', 'wait_until']);
            $table->index(['wait_for_message', 'status']);
        });

        // ─────────────────────────────────────────────────
        // Variables — متغیرهای instance
        // ─────────────────────────────────────────────────
        Schema::create('process_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('scope_token_id')->nullable()
                ->constrained('process_tokens')
                ->comment('اگر scoped به یک token خاص است');

            $table->string('name', 100);
            $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json', 'date', 'datetime', 'reference']);

            // مقدار به‌صورت multi-column برای جستجوی سریع
            $table->text('string_value')->nullable();
            $table->bigInteger('integer_value')->nullable();
            $table->double('float_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->json('json_value')->nullable();
            $table->timestamp('datetime_value')->nullable();
            $table->string('reference_type', 200)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();

            $table->unique(['instance_id', 'scope_token_id', 'name']);
            $table->index(['instance_id', 'name']);
        });

        // ─────────────────────────────────────────────────
        // History — رویدادهای اجرا (append-only)
        // ─────────────────────────────────────────────────
        Schema::create('process_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()
                ->constrained('process_tokens')->nullOnDelete();

            $table->string('event_type', 50)
                ->comment('instance_started / element_entered / element_exited / element_completed / token_split / token_joined / instance_completed / instance_failed / ...');
            $table->string('element_id', 100)->nullable();
            $table->string('element_type', 80)->nullable();
            $table->string('element_name')->nullable();

            $table->json('payload')->nullable()
                ->comment('داده‌های مرتبط با event');

            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->timestamp('occurred_at')->index();

            // append-only — بدون updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['instance_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_history');
        Schema::dropIfExists('process_variables');
        Schema::dropIfExists('process_tokens');
        Schema::dropIfExists('process_instances');
    }
};
