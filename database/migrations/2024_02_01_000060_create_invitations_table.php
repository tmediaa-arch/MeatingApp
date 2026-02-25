<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // دعوت‌نامه‌ها — هر ارسال یک رکورد
        // هر participant ممکن است چند invitation داشته باشد (یادآوری‌ها)
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('meeting_participants')->cascadeOnDelete();

            // نوع — invitation اولیه vs reminder
            $table->enum('type', [
                'invitation',       // دعوت اولیه
                'reschedule',       // اطلاع تغییر زمان
                'reminder',         // یادآوری
                'cancellation',     // اعلام لغو
                'update',           // تغییر دستور یا مکان
                'minutes_ready',    // صورتجلسه آماده شد (فاز ۳)
            ])->default('invitation');

            // کانال
            $table->enum('channel', [
                'email',
                'sms',
                'in_app',           // اعلان داخل سامانه
                'push',             // mobile push (آینده)
                'ical',             // فایل ICS ضمیمه ایمیل
            ])->default('email');

            // ارسال
            $table->string('to_address', 200)->comment('email یا mobile یا user_id');
            $table->string('subject', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('ical_uid', 100)->nullable()->comment('UID در فایل ICS برای آپدیت بعدی');
            $table->unsignedTinyInteger('ical_sequence')->default(0)
                ->comment('شماره revision در ICS — برای آپدیت‌های متوالی');

            // وضعیت ارسال
            $table->enum('status', [
                'queued',     // در صف ارسال
                'sending',    // در حال ارسال
                'sent',       // ارسال شد
                'delivered',  // تحویل داده شد (در صورت پشتیبانی provider)
                'bounced',    // برگشت خورد
                'failed',     // خطا
                'opened',     // باز شد (پیگیری از طریق tracking pixel)
                'clicked',    // لینک کلیک شد
            ])->default('queued');

            $table->dateTime('scheduled_at')->nullable()->comment('زمان ارسال در صورت reminder');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('responded_at')->nullable();

            // خطا
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->dateTime('next_retry_at')->nullable();

            // ID خارجی (از provider مثل sendgrid، kavenegar)
            $table->string('external_id', 200)->nullable();

            // token برای پاسخ مستقیم (accept/decline) از طریق لینک ایمیل
            $table->string('response_token', 100)->nullable()->unique();
            $table->dateTime('response_token_expires_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'type']);
            $table->index('participant_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['status', 'next_retry_at']);
        });

        // پاسخ‌های ثبت شده (تاریخچه پاسخ‌دهی)
        Schema::create('invitation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('meeting_participants')->cascadeOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();

            $table->enum('response', ['accepted', 'declined', 'tentative']);
            $table->text('note')->nullable();

            // به نمایندگی از چه کسی پاسخ داد
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('user_delegations')->nullOnDelete();

            // در صورت decline، آیا نماینده معرفی کرد؟
            $table->foreignId('proposed_substitute_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            // روش پاسخ (web, email-link, sms-reply, api)
            $table->string('response_method', 50)->default('web');
            $table->string('response_ip', 45)->nullable();

            $table->dateTime('responded_at');
            $table->timestamps();

            $table->index('participant_id');
            $table->index('responded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_responses');
        Schema::dropIfExists('invitations');
    }
};
