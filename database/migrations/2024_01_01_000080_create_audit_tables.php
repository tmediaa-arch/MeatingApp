<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Domain — Audit Logs, Login Logs, Security Events
 *
 * این جداول append-only هستند. هیچ UPDATE یا DELETE روی آن‌ها مجاز نیست.
 * این قانون در سطح اپلیکیشن (Model abort می‌کند) و در سطح دیتابیس
 * (DBA باید REVOKE UPDATE/DELETE بدهد) رعایت می‌شود.
 *
 * audit_logs:
 *   هر تغییر روی هر موجودیت حساس را ثبت می‌کند.
 *   شامل old_values, new_values, changed_fields.
 *   برای performance، old/new به‌صورت JSON ذخیره می‌شوند، نه ستون‌های جدا.
 *
 * login_logs:
 *   هر تلاش ورود (موفق یا ناموفق) ثبت می‌شود.
 *   به‌جز IP و User Agent، fingerprint مرورگر هم می‌تواند اضافه شود.
 *
 * security_events:
 *   رویدادهای امنیتی نرمال نیست — تشخیص رفتار غیرعادی.
 *   مثل: ۱۰۰ تلاش ناموفق در یک دقیقه، دانلود ۵۰ فایل محرمانه پشت سر هم،
 *   تغییر نقش Super Admin، تلاش دسترسی به فرایند bypass شده.
 *
 * چرا برای performance partitioning نیاز است:
 *   audit_logs بسیار سریع رشد می‌کند. در PostgreSQL از table partitioning
 *   ماهانه پشتیبانی می‌کنیم. در حال حاضر فقط ایندکس‌های قوی می‌گذاریم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // چه کسی
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_display_name', 200)->nullable()
                ->comment('snapshot از نام کاربر در لحظه ثبت — در صورت حذف کاربر هم باقی می‌ماند');

            // اگر کاربر در حالت تفویض اقدام کرده
            $table->foreignId('on_behalf_of_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('user_delegations')->nullOnDelete();

            // چه چیزی (موجودیت هدف)
            $table->string('auditable_type', 100)->index();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // چه کاری
            $table->string('event', 50)->index()
                ->comment('created|updated|deleted|restored|approved|rejected|signed|...');
            $table->string('action_category', 50)->nullable()->index()
                ->comment('گروه‌بندی برای گزارش‌گیری');

            // تغییرات
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable()
                ->comment('فقط نام فیلدهایی که تغییر کردند — برای جستجوی سریع');

            // زمینه
            $table->string('description', 500)->nullable()
                ->comment('توضیح خوانا برای انسان');
            $table->json('context')->nullable()
                ->comment('اطلاعات اضافی: meeting_id, workflow_id, ...');

            // درخواست HTTP
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_url', 500)->nullable();
            $table->string('request_id', 36)->nullable()->index()
                ->comment('UUID برای ربط دادن چند audit log به یک request');

            // tag و کلید همبستگی
            $table->string('tag', 50)->nullable()->index();
            $table->string('correlation_id', 36)->nullable()->index()
                ->comment('برای ربط دادن actions مرتبط مثل ایجاد جلسه + دعوت‌ها');

            // سطح اهمیت
            $table->string('severity', 20)->default('info')->index()
                ->comment('debug|info|notice|warning|critical');

            $table->timestamp('performed_at')->useCurrent()->index();
            // No updated_at, No softDeletes — append-only

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'performed_at']);
            $table->index(['event', 'performed_at']);
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username_attempted', 200)->nullable()
                ->comment('username که سعی کرده وارد شود — حتی اگر وجود نداشته');

            $table->string('result', 30)->index()
                ->comment('success|failed_credentials|locked|mfa_required|mfa_failed|disabled|expired');
            $table->string('auth_method', 30)->default('password')
                ->comment('password|ldap|sso|api_token|impersonation');

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_fingerprint', 100)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('city', 100)->nullable();

            // برای logout
            $table->string('session_id', 100)->nullable()->index();
            $table->timestamp('logged_out_at')->nullable();
            $table->string('logout_reason', 30)->nullable()
                ->comment('user|timeout|forced|password_changed');

            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent()->index();

            $table->index(['user_id', 'performed_at']);
            $table->index(['result', 'performed_at']);
            $table->index(['ip_address', 'performed_at']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('event_type', 50)->index()
                ->comment('brute_force|suspicious_login|privilege_escalation|...');
            $table->string('severity', 20)->index()
                ->comment('low|medium|high|critical');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ipAddress('ip_address')->nullable()->index();

            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('evidence')->nullable()
                ->comment('شواهد: تعداد تلاش‌ها، query استفاده شده، ...');

            // پاسخ خودکار
            $table->boolean('auto_blocked')->default(false);
            $table->boolean('notified_admins')->default(false);

            // بررسی توسط ادمین
            $table->string('status', 30)->default('open')->index()
                ->comment('open|under_review|resolved|false_positive|ignored');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamp('performed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('audit_logs');
    }
};
