<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Domain — Job Titles & Positions
 *
 * تفاوت مفهومی مهم:
 * - job_titles: عناوین شغلی استاندارد (مثلاً «کارشناس مالی»، «مدیر فناوری اطلاعات»)
 *   که در سرتاسر سازمان قابل استفاده‌اند. مثل dictionary.
 *
 * - positions: پست‌های سازمانی واقعی. یک پست در یک واحد مشخص با یک عنوان مشخص.
 *   چند پست می‌توانند هم‌عنوان باشند (مثلاً ۳ پست «کارشناس مالی» در ۳ واحد متفاوت).
 *   پست می‌تواند بدون متصدی باشد (employee_id = null در جدول employees).
 *
 * این تفکیک از تجربه پروژه‌های دولتی ایران آمده — جایی که ساختار اداری
 * بسیار سفت‌وسخت تعریف می‌شود و «پست بدون متصدی» مفهومی رسمی است.
 */
return new class extends Migration
{
    public function up(): void
    {
        // عناوین شغلی — Dictionary
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('english_name', 200)->nullable();
            $table->text('description')->nullable();

            // سطح مدیریتی (برای تشخیص خودکار managerial role)
            $table->string('management_level', 30)->nullable()
                ->comment('executive|senior_manager|manager|supervisor|expert|operational');

            $table->unsignedInteger('rank')->default(0)
                ->comment('رتبه شغلی — برای مرتب‌سازی و escalation');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'management_level']);
        });

        // پست‌های سازمانی — موجودیت‌های واقعی
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->foreignId('job_title_id')->constrained('job_titles');

            $table->string('code', 50)->comment('شماره پست');
            $table->string('title', 200)->comment('عنوان پست — می‌تواند با job_title متفاوت باشد');
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();

            // پست به طور موقت بدون متصدی است یا حذف شده
            $table->string('status', 20)->default('vacant')->index()
                ->comment('vacant|occupied|frozen|abolished');

            // ویژگی‌های اختیاری
            $table->boolean('is_managerial')->default(false)
                ->comment('این پست مدیریت یک واحد را بر عهده دارد؟');
            $table->boolean('can_chair_meetings')->default(true);
            $table->boolean('requires_security_clearance')->default(false);
            $table->string('max_clearance_level')->nullable()
                ->comment('بالاترین سطح محرمانگی مجاز برای متصدی این پست');

            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['org_unit_id', 'status']);
            $table->index(['is_managerial', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('job_titles');
    }
};
