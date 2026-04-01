<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قالب‌های اعلان — قابل ویرایش توسط ادمین
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            // کلید قالب: meeting.invitation, meeting.reminder, task.assigned, ...
            $table->string('key', 100);
            $table->string('display_name', 200);
            $table->text('description')->nullable();

            // کانال‌ها — یک template می‌تواند چند کانال داشته باشد
            // محتوای هر کانال در جدول notification_template_channels
            $table->json('supported_channels')->nullable();

            // متغیرهای قابل استفاده در template — placeholderهای {{ var }}
            $table->json('available_variables')->nullable();

            // آیا قابل غیرفعال شدن توسط کاربر است؟
            $table->boolean('is_user_disablable')->default(true);

            // آیا قابل توسط ادمین تغییر است؟
            $table->boolean('is_admin_editable')->default(true);

            // اولویت‌بندی
            $table->string('priority', 20)->default('normal'); // critical, high, normal, low

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'key']);
            $table->index('is_active');
        });

        // محتوای قالب به تفکیک کانال
        Schema::create('notification_template_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->string('channel', 20); // email, sms, in_app, push

            // subject فقط برای email و in_app
            $table->string('subject', 500)->nullable();
            // body با blade syntax یا simple placeholder
            $table->longText('body');
            // برای email می‌تواند html باشد
            $table->longText('body_html')->nullable();

            // متادیتا
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['template_id', 'channel']);
        });

        // اعلان‌های ارسال شده / در صف
        Schema::create('notifications_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();

            // template ای که از آن استفاده شده
            $table->foreignId('template_id')->nullable()
                ->constrained('notification_templates')->nullOnDelete();

            // به چه کسی
            $table->foreignId('recipient_user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->string('channel', 20)->index(); // email, sms, in_app, push, webhook
            $table->string('to_address', 500); // email, phone, user_id, url, ...

            $table->string('subject', 500)->nullable();
            $table->longText('body');
            $table->longText('body_html')->nullable();

            // مرجع به منبع تولید اعلان (polymorphic) — nullable چون
            // برخی اعلان‌ها ممکن است entity خاصی نداشته باشند (مثلاً welcome message)
            $table->nullableMorphs('notifiable'); // notifiable_type, notifiable_id

            // وضعیت ارسال
            // pending, sent, delivered, opened, clicked, failed, cancelled
            $table->string('status', 20)->default('pending')->index();

            $table->string('priority', 20)->default('normal');

            // زمان‌بندی
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();

            // retry mechanism
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            // برای کارتابل (Inbox) — آیا کاربر دیده؟
            $table->boolean('read_in_inbox')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->boolean('archived_in_inbox')->default(false);

            // متادیتا - برای webhook/email tracking
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['recipient_user_id', 'channel', 'status']);
            $table->index(['recipient_user_id', 'read_in_inbox']);
        });

        // ترجیحات کاربر برای دریافت اعلان
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('template_key', 100); // matches notification_templates.key

            // فعال/غیرفعال در هر کانال
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);

            // ساعت دریافت ترجیحی (DND هم)
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'template_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications_outbox');
        Schema::dropIfExists('notification_template_channels');
        Schema::dropIfExists('notification_templates');
    }
};
