<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // دستور جلسه به‌صورت ساختاریافته
        // این جدول جدا از meetings.agenda_items (که snapshot است) نگهداری می‌شود
        // تا قابلیت reordering، تخصیص ارائه‌دهنده، و ثبت زمان واقعی بحث را داشته باشیم.
        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();

            $table->unsignedSmallInteger('order_index')->default(0);
            $table->string('title', 500);
            $table->text('description')->nullable();

            // ارائه‌دهنده دستور
            $table->foreignId('presenter_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->string('presenter_external', 200)->nullable()
                ->comment('در صورت ارائه‌دهنده بیرونی');

            // مدت زمان
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(15);
            $table->unsignedSmallInteger('actual_duration_minutes')->nullable();
            $table->dateTime('actual_started_at')->nullable();
            $table->dateTime('actual_ended_at')->nullable();

            // نوع
            $table->enum('item_type', [
                'discussion',    // بحث
                'decision',      // تصمیم‌گیری
                'information',   // اطلاع‌رسانی
                'presentation',  // ارائه
                'voting',        // رأی‌گیری
                'review',        // بازبینی مصوبات قبلی
                'other',
            ])->default('discussion');

            // وضعیت در جلسه
            $table->enum('status', [
                'pending',     // قبل از جلسه
                'in_progress', // در حال بحث
                'discussed',   // بحث شد
                'deferred',    // به جلسه بعد موکول
                'cancelled',   // لغو در جلسه
            ])->default('pending');

            // پیوست‌ها (لینک به دامنه Files در فاز ۳)
            $table->json('attachment_refs')->nullable();

            // نتایج اولیه (نهایی در دامنه Minutes فاز ۳ خواهد بود)
            $table->text('summary_notes')->nullable()->comment('چکیده بحث برای ضبط در صورتجلسه');
            $table->json('initial_decisions')->nullable()->comment('تصمیمات اولیه که به دامنه Resolutions منتقل می‌شود');

            $table->timestamps();

            $table->index(['meeting_id', 'order_index']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_agenda_items');
    }
};
