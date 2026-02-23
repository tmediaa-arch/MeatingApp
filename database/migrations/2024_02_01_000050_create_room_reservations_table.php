<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // رزرو سالن — جدا از meetings برای پشتیبانی از:
        // 1. رزرو بدون meeting (مثلاً برای نگهداری، تعمیرات)
        // 2. ردیابی تاریخچه رزرو و conflicts
        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();

            // مرجع — meeting یا maintenance
            $table->foreignId('meeting_id')->nullable()
                ->constrained('meetings')->cascadeOnDelete();
            $table->enum('reservation_type', [
                'meeting',     // برای جلسه
                'maintenance', // نگهداری
                'event',       // رویداد (غیرجلسه)
                'block',       // مسدودی موقت
            ])->default('meeting');

            // بازه واقعی (شامل buffer ها)
            $table->dateTime('reserved_from');
            $table->dateTime('reserved_until');

            // بازه واقعی استفاده (بدون buffer) — برای نمایش به کاربر
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();

            // درخواست‌دهنده
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->unsignedSmallInteger('expected_attendees')->nullable();

            // وضعیت
            $table->enum('status', [
                'pending',    // در انتظار تأیید
                'approved',   // تأیید شد
                'rejected',   // رد شد
                'cancelled',  // لغو شد توسط متقاضی
                'completed',  // برگزار شد
                'no_show',    // متقاضی حاضر نشد
            ])->default('pending');

            // تأیید/رد
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // override (در صورت نیاز فوری توسط super user)
            $table->boolean('is_override')->default(false);
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();

            // نیازمندی‌های ویژه
            $table->json('special_requirements')->nullable()
                ->comment('چیدمان سفارشی، تجهیزات اضافه، پذیرایی، ...');

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('room_id');
            $table->index('meeting_id');
            $table->index(['room_id', 'reserved_from', 'reserved_until'], 'room_resv_room_time_idx');
            $table->index(['reserved_from', 'reserved_until'], 'room_resv_time_idx');
            $table->index('status');
            $table->index('requested_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_reservations');
    }
};
