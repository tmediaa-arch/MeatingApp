<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول تعاریف فرایند (BPMN definitions).
 *
 * هر ProcessDefinition یک نسخه از یک فرایند است.
 * نسخه‌ها append-only هستند — وقتی فرایند ویرایش می‌شود،
 * یک نسخه جدید با شماره بالاتر ایجاد می‌شود و نسخه قبلی
 * در حالت deprecated باقی می‌ماند (instance های اجرا شده
 * با نسخه قبلی همچنان قابل follow هستند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            $table->string('process_key', 100)
                ->comment('کلید یکتای فرایند — مثل meeting_minute_approval');
            $table->integer('version')->default(1)
                ->comment('شماره نسخه فرایند');

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable()
                ->comment('دسته‌بندی — meeting / task / approval / system');

            // محتوای BPMN XML
            $table->longText('bpmn_xml')
                ->comment('XML کامل فرایند BPMN 2.0');
            $table->string('bpmn_xml_hash', 64)
                ->comment('SHA-256 هش XML برای تشخیص تغییر');

            // فرم metadata (پارس شده از BPMN)
            $table->json('parsed_metadata')->nullable()
                ->comment('متادیتای استخراج‌شده: عناصر، خروجی‌ها، service tasks، ...');
            $table->json('start_form_schema')->nullable()
                ->comment('schema فرم شروع فرایند');
            $table->json('variables_schema')->nullable()
                ->comment('schema متغیرهای پیش‌فرض فرایند');

            // وضعیت
            $table->enum('status', ['draft', 'published', 'deprecated', 'archived'])
                ->default('draft');
            $table->boolean('is_latest')->default(false)
                ->comment('آیا آخرین نسخه از این process_key است؟');

            // اطلاعات publish
            $table->foreignId('published_by_user_id')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();

            // اطلاعات ایجادکننده
            $table->foreignId('creator_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // یکتایی نسخه‌ها در ساحه org/key
            $table->unique(['organization_id', 'process_key', 'version']);
            $table->index(['organization_id', 'process_key', 'is_latest']);
            $table->index(['status', 'is_latest']);
        });

        // ─────────────────────────────────────────────────
        // عناصر استخراج‌شده از BPMN — برای جستجوی سریع و audit
        // این جدول append-only نیست ولی محتوای آن از XML مشتق
        // می‌شود و هنگام publish پر می‌شود.
        // ─────────────────────────────────────────────────
        Schema::create('process_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_definition_id')->constrained('process_definitions')->cascadeOnDelete();

            $table->string('element_id', 100)
                ->comment('id عنصر در BPMN — مثل StartEvent_1');
            $table->string('element_type', 80)
                ->comment('نوع — startEvent / endEvent / userTask / serviceTask / exclusiveGateway / ...');
            $table->string('name')->nullable();

            // metadata اختصاصی نوع عنصر
            $table->json('properties')->nullable()
                ->comment('پراپرتی‌های اختصاصی — assignee_expression، due_date_expression، ...');
            $table->json('form_schema')->nullable()
                ->comment('schema فرم برای user task');

            // برای service task
            $table->string('service_task_class', 200)->nullable()
                ->comment('کلاس Service Task — باید در whitelist باشد');
            $table->json('service_task_config')->nullable();

            // ترتیب پیمایش (تقریبی)
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['process_definition_id', 'element_id']);
            $table->index(['process_definition_id', 'element_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_elements');
        Schema::dropIfExists('process_definitions');
    }
};
