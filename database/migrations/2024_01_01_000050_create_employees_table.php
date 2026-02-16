<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Domain — Employees
 *
 * یک‌به‌یک با users. employees جداست از users چون:
 * - مهمان بیرونی user دارد ولی employee ندارد
 * - employee اطلاعات HR-محور دارد که هویتی نیست
 * - employee به position و org_unit متصل است
 * - تاریخچه پست‌های employee در employee_position_histories نگه‌داری می‌شود
 *
 * یک کارمند ممکن است هم‌زمان چند پست داشته باشد (پست اصلی + جانشینی موقت)،
 * اما primary_position_id همیشه یکی است و در جلسات از همان استفاده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            // کد پرسنلی (یکتا در سازمان)
            $table->string('employee_code', 50);
            $table->string('national_code', 10)->nullable()->index();

            // اطلاعات شخصی (که در users ممکن است نباشد، چون user می‌تواند مهمان باشد)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('father_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();

            // اطلاعات تماس سازمانی
            $table->string('work_email', 200)->nullable();
            $table->string('work_phone', 50)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('office_location', 200)->nullable();

            // پست اصلی و واحد
            $table->foreignId('primary_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('current_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();

            // مدیر مستقیم (یک کارمند به کارمند دیگری گزارش می‌دهد — هرم گزارش‌دهی)
            $table->foreignId('reports_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // وضعیت استخدامی
            $table->string('employment_status', 30)->default('active')->index()
                ->comment('active|on_leave|suspended|retired|resigned|terminated');
            $table->string('employment_type', 30)->nullable()
                ->comment('permanent|contract|temporary|consultant|trainee');

            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();

            // سطح دسترسی محرمانگی
            $table->string('clearance_level', 20)->default('internal')
                ->comment('public|internal|confidential|secret');

            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'employee_code']);
            $table->index(['employment_status', 'organization_id']);
            $table->index(['current_org_unit_id', 'employment_status']);
            $table->index(['last_name', 'first_name']);
        });

        // FK برگشتی از users به employees (الان که employees ساخته شد)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        // FK برگشتی از org_units به employees (manager_employee_id)
        Schema::table('org_units', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('employees');
    }
};
