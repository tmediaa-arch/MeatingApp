<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // جلسات — هسته اصلی سامانه
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();

            // شناسایی سازمانی
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('host_org_unit_id')->constrained('org_units')->restrictOnDelete()
                ->comment('واحد میزبان جلسه');

            // شماره و عنوان
            $table->string('meeting_number', 50)->unique()->comment('شماره خودکار: ORG-YYYY-NNNN');
            $table->string('subject', 500);
            $table->text('description')->nullable();
            $table->json('agenda_items')->nullable()->comment('دستور جلسه — لیست موارد');

            // نوع
            $table->enum('type', [
                'regular',         // عادی
                'extraordinary',   // فوق‌العاده
                'committee',       // کمیسیون
                'working_group',   // کارگروه
                'board',           // هیئت‌مدیره
                'general_assembly',// مجمع عمومی
                'other',
            ])->default('regular');

            $table->enum('mode', [
                'in_person',  // حضوری
                'online',     // آنلاین
                'hybrid',     // ترکیبی
            ])->default('in_person');

            $table->enum('recurrence_pattern', [
                'none',     // یکبار
                'daily',
                'weekly',
                'monthly',
                'custom',
            ])->default('none');
            $table->json('recurrence_config')->nullable()
                ->comment('پیکربندی تکرار: {interval, until, by_day, ...}');
            $table->foreignId('recurrence_parent_id')->nullable()
                ->constrained('meetings')->nullOnDelete()
                ->comment('در instance های تکرارشونده، اشاره به meeting اصلی');

            // محرمانگی
            $table->enum('confidentiality_level', ['public', 'internal', 'confidential', 'secret'])
                ->default('internal');

            // زمان‌بندی
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->string('timezone', 50)->default('Asia/Tehran');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();

            // مکان
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('location_alt', 500)->nullable()
                ->comment('در صورت نبود سالن داخلی — مکان جایگزین');

            // ویدئوکنفرانس (فاز ۵ کامل می‌شود؛ اینجا فقط placeholder)
            $table->string('video_provider', 50)->nullable()->comment('alocom|jitsi|bbb|manual');
            $table->string('video_meeting_url', 500)->nullable();
            $table->string('video_meeting_id', 100)->nullable();
            $table->string('video_host_url', 500)->nullable();
            $table->json('video_metadata')->nullable();

            // افراد کلیدی
            $table->foreignId('chairperson_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete()
                ->comment('رئیس جلسه');
            $table->foreignId('secretary_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete()
                ->comment('دبیر جلسه — مسئول صورتجلسه');
            $table->foreignId('creator_user_id')->constrained('users')->restrictOnDelete()
                ->comment('ایجاد کننده جلسه — معمولاً دفتر معاون یا دبیر');

            // وضعیت چرخه عمر
            $table->enum('status', [
                'draft',         // پیش‌نویس
                'scheduled',     // برنامه‌ریزی شده
                'invitations_sent', // دعوت‌نامه‌ها ارسال شد
                'in_progress',   // در حال برگزاری
                'paused',        // متوقف موقت
                'completed',     // برگزار شد
                'cancelled',     // لغو شد
                'postponed',     // به تعویق افتاد
            ])->default('draft');

            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();

            // تنظیمات
            $table->boolean('allow_external_participants')->default(false);
            $table->boolean('require_confirmation')->default(true)
                ->comment('آیا پاسخ مدعوین الزامی است؟');
            $table->boolean('record_attendance')->default(true);
            $table->boolean('send_reminder')->default(true);
            $table->unsignedSmallInteger('reminder_minutes_before')->default(60);
            $table->boolean('allow_late_join')->default(true);

            // ضبط و تصویر
            $table->boolean('is_recorded')->default(false);
            $table->string('recording_url', 500)->nullable();

            // متادیتا
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            // tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها (بحرانی برای تقویم)
            $table->index('organization_id');
            $table->index('host_org_unit_id');
            $table->index(['scheduled_start_at', 'scheduled_end_at'], 'meetings_schedule_idx');
            $table->index('status');
            $table->index('room_id');
            $table->index('chairperson_employee_id');
            $table->index('secretary_employee_id');
            $table->index('creator_user_id');
            $table->index('confidentiality_level');
            $table->index(['organization_id', 'status', 'scheduled_start_at'], 'meetings_org_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
