<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول دامنه VideoConference:
 *  - video_conference_providers: تنظیمات ارائه‌دهندگان (Alocom, Jitsi, BBB, Null)
 *  - video_conference_rooms: اتاق‌های ایجادشده در providerها
 *  - video_conference_attendance: لاگ حضور کاربران در اتاق‌ها
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────
        // تنظیمات provider — هر سازمان می‌تواند چند provider
        // پیکربندی کند و یکی را به‌عنوان پیش‌فرض داشته باشد
        // ─────────────────────────────────────────────────
        Schema::create('video_conference_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            $table->string('name')
                ->comment('نام دلخواه — مثلاً Alocom سازمانی');
            $table->enum('driver', ['alocom', 'jitsi', 'bigbluebutton', 'null'])
                ->comment('کلید adapter');

            // تنظیمات driver (api key, base url, etc.) — encrypted
            $table->text('config_encrypted')
                ->comment('تنظیمات provider به‌صورت رمزنگاری‌شده JSON');

            // توان و حد مجاز
            $table->integer('max_concurrent_meetings')->nullable()
                ->comment('حداکثر همزمانی جلسات (null = نامحدود)');
            $table->integer('max_participants_per_meeting')->nullable();

            $table->boolean('supports_recording')->default(false);
            $table->boolean('supports_streaming')->default(false);
            $table->boolean('supports_breakout_rooms')->default(false);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)
                ->comment('در صورت true، انتخاب پیش‌فرض در ایجاد اتاق');

            $table->timestamp('last_health_check_at')->nullable();
            $table->enum('health_status', ['unknown', 'healthy', 'degraded', 'unhealthy'])
                ->default('unknown');
            $table->text('health_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'is_active', 'is_default']);
        });

        // ─────────────────────────────────────────────────
        // اتاق‌های ایجاد شده — هر اتاق متعلق به یک جلسه است
        // ─────────────────────────────────────────────────
        Schema::create('video_conference_rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_uuid')->unique()
                ->comment('شناسه یکتای داخلی برای رهگیری');

            $table->foreignId('meeting_id')->nullable()
                ->constrained('meetings')->nullOnDelete();
            $table->foreignId('provider_id')->constrained('video_conference_providers');
            $table->string('driver', 50);

            // اطلاعات بازگشتی از provider
            $table->string('external_room_id', 200)
                ->comment('شناسه‌ای که خود provider تولید کرده');
            $table->string('host_url', 2000)
                ->comment('لینک ورود میزبان (دارای کلید موقت)');
            $table->string('attendee_url', 2000)
                ->comment('لینک ورود مدعو');
            $table->string('moderator_password', 200)->nullable();
            $table->string('attendee_password', 200)->nullable();

            // پیکربندی اتاق
            $table->string('subject');
            $table->integer('max_participants')->nullable();
            $table->boolean('require_password')->default(false);
            $table->boolean('waiting_room_enabled')->default(false);
            $table->boolean('recording_enabled')->default(false);

            // زمان‌بندی
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();

            // وضعیت
            $table->enum('status', [
                'scheduled',     // ایجاد شده، هنوز شروع نشده
                'starting',      // در حال شروع
                'in_progress',   // در حال برگزاری
                'ended',         // پایان یافت
                'cancelled',     // لغو شد
                'failed',        // خطا در ایجاد در provider
            ])->default('scheduled');

            // ضبط
            $table->string('recording_url', 2000)->nullable();
            $table->string('recording_status', 50)->nullable()
                ->comment('not_recording / recording / processing / available / failed');
            $table->bigInteger('recording_duration_seconds')->nullable();
            $table->bigInteger('recording_size_bytes')->nullable();

            $table->json('provider_metadata')->nullable()
                ->comment('داده‌های اضافی از provider');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['meeting_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index('scheduled_start_at');
        });

        // ─────────────────────────────────────────────────
        // ثبت حضور — append-only
        // به ازای هر join/leave یک رکورد ایجاد می‌شود
        // ─────────────────────────────────────────────────
        Schema::create('video_conference_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('video_conference_rooms')->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();

            // شرکت‌کننده
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->string('display_name', 200);
            $table->string('email', 200)->nullable();

            $table->enum('role', ['host', 'moderator', 'attendee', 'guest'])->default('attendee');
            $table->enum('event_type', ['joined', 'left'])
                ->comment('append-only: هر join/leave یک رکورد');

            $table->timestamp('occurred_at');
            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('external_session_id', 200)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['room_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['meeting_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_conference_attendance');
        Schema::dropIfExists('video_conference_rooms');
        Schema::dropIfExists('video_conference_providers');
    }
};
