<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // سالن‌های جلسات
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('owner_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete()
                ->comment('واحد سازمانی صاحب سالن — برای تعیین مسئول رزرو');

            // شناسایی
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('english_name', 200)->nullable();
            $table->text('description')->nullable();

            // ظرفیت
            $table->unsignedSmallInteger('capacity')->comment('ظرفیت استاندارد');
            $table->unsignedSmallInteger('max_capacity')->nullable()->comment('حداکثر ظرفیت در شرایط ویژه');
            $table->enum('layout_type', [
                'classroom',      // کلاسی
                'u_shape',        // U شکل
                'round_table',    // میز گرد
                'theater',        // تئاتری
                'boardroom',      // اتاق هیئت‌مدیره
                'open',           // باز / مدولار
                'mixed',          // ترکیبی
            ])->default('boardroom');

            // مکان
            $table->string('building', 100)->nullable();
            $table->string('floor', 50)->nullable();
            $table->string('room_number', 50)->nullable();
            $table->text('directions')->nullable()->comment('راهنمای دسترسی');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // تجهیزات (JSON list) و امکانات (boolean flags)
            $table->json('equipment')->nullable()->comment('لیست تجهیزات: پروژکتور، ویدئوکنفرانس، ...');
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_video_conference')->default(false);
            $table->boolean('has_whiteboard')->default(false);
            $table->boolean('has_audio_system')->default(false);
            $table->boolean('has_recording')->default(false);
            $table->boolean('has_wifi')->default(true);
            $table->boolean('has_accessibility')->default(false)->comment('قابل دسترسی برای معلولین');

            // سیاست رزرو
            $table->enum('reservation_policy', [
                'free',         // بدون نیاز به تأیید
                'approval',     // نیاز به تأیید مسئول
                'restricted',   // فقط افراد مجاز
            ])->default('approval');

            $table->unsignedSmallInteger('min_booking_minutes')->default(30)->comment('حداقل مدت رزرو (دقیقه)');
            $table->unsignedSmallInteger('max_booking_minutes')->default(480)->comment('حداکثر مدت رزرو (دقیقه)');
            $table->unsignedSmallInteger('buffer_before_minutes')->default(15)->comment('فاصله قبل از رزرو (آماده‌سازی)');
            $table->unsignedSmallInteger('buffer_after_minutes')->default(15)->comment('فاصله بعد از رزرو (نظافت)');
            $table->unsignedSmallInteger('advance_booking_days')->default(60)->comment('حداکثر روز پیش‌رزرو');

            // ساعات کاری (JSON: روزهای هفته با start/end)
            $table->json('working_hours')->nullable()->comment('ساعات کاری: {sat: {start: 08:00, end: 17:00}, ...}');

            // وضعیت
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->date('activated_at')->nullable();
            $table->date('decommissioned_at')->nullable();

            // محرمانگی — فقط افرادی با clearance کافی می‌توانند رزرو کنند
            $table->enum('confidentiality_level', ['public', 'internal', 'confidential', 'secret'])
                ->default('internal');

            // متادیتا
            $table->json('photos')->nullable()->comment('مسیر تصاویر سالن');
            $table->json('metadata')->nullable();

            // tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها
            $table->index('organization_id');
            $table->index('owner_org_unit_id');
            $table->index('status');
            $table->index(['organization_id', 'status']);
            $table->index('confidentiality_level');
        });

        // مدیران مجاز رزرو هر سالن (M2M)
        Schema::create('room_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['manager', 'approver', 'viewer'])->default('manager');
            $table->boolean('receive_notifications')->default(true);
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
        });

        // افراد/واحدهای مجاز برای رزرو سالن‌های restricted
        Schema::create('room_allowed_principals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('principal_type', 50)->comment('user | org_unit | role');
            $table->unsignedBigInteger('principal_id');
            $table->timestamps();

            $table->unique(['room_id', 'principal_type', 'principal_id'], 'room_allowed_principals_unique');
            $table->index(['principal_type', 'principal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_allowed_principals');
        Schema::dropIfExists('room_managers');
        Schema::dropIfExists('rooms');
    }
};
