<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity Domain — Users
 *
 * جدول هسته احراز هویت.
 *
 * نکات طراحی:
 * - employee_id به‌صورت soft pointer ذخیره می‌شود (unsignedBigInteger nullable)
 *   و FK واقعی در migration بعدی (پس از ایجاد employees) اضافه می‌شود.
 *   این الگو circular dependency بین users و employees را حل می‌کند.
 * - is_external برای مهمان بیرونی است (employee_id خواهد بود null)
 * - locked_until برای سیاست brute-force protection
 * - mfa_secret حتماً encrypted ذخیره می‌شود (در سطح Model با $casts)
 *
 * چرا created_by ندارد در ابتدا؟
 * چون اولین user (super admin) خودش است؛ FK self-referential نمی‌گذاریم
 * و در سطح اپلیکیشن مدیریت می‌کنیم (audit log جداگانه).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // مشخصات هویتی
            $table->string('username', 100)->unique();
            $table->string('email', 200)->nullable()->unique();
            $table->string('national_code', 10)->nullable()->unique()
                ->comment('کد ملی برای کاربران ایرانی');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('display_name', 200)->nullable();
            $table->string('mobile', 20)->nullable()->index();
            $table->string('phone', 20)->nullable();
            $table->string('avatar_path')->nullable();

            // احراز هویت
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->rememberToken();

            // وضعیت
            $table->string('status', 20)->default('active')->index()
                ->comment('active|suspended|locked|expired|pending');
            $table->boolean('is_external')->default(false)->index();
            $table->boolean('is_system')->default(false)
                ->comment('کاربر سیستمی — قابل حذف نیست');

            // MFA
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable()->comment('encrypted');
            $table->text('mfa_recovery_codes')->nullable()->comment('encrypted JSON');

            // یکپارچه‌سازی
            $table->string('ldap_guid', 100)->nullable()->unique();
            $table->string('ldap_domain', 100)->nullable();
            $table->string('sso_subject', 200)->nullable()->index();
            $table->string('hrs_employee_code', 50)->nullable()->index();

            // ارجاع به کارمند — FK بعداً اضافه می‌شود
            $table->unsignedBigInteger('employee_id')->nullable()->unique();

            // ردیابی امنیتی
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->string('last_login_user_agent', 500)->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // اولویت‌های کاربری
            $table->string('preferred_locale', 10)->default('fa');
            $table->string('preferred_calendar', 20)->default('jalali');
            $table->string('timezone', 50)->default('Asia/Tehran');
            $table->json('notification_preferences')->nullable();

            // ردیابی تغییرات
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌های ترکیبی
            $table->index(['status', 'is_external']);
            $table->index(['last_name', 'first_name']);
        });

        // Self-referencing FKs بعد از ایجاد جدول
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // جداول استاندارد لاراول
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Personal access tokens برای Sanctum API
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });
        Schema::dropIfExists('users');
    }
};
