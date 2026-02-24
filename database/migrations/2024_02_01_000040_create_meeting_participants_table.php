<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // مدعوین و شرکت‌کنندگان جلسه
        // پشتیبانی هم از employee (داخلی) و هم external (مهمان)
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();

            // یا employee_id یا external_* — دقیقاً یکی
            $table->foreignId('employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('در صورت داشتن user account برای login');

            // اطلاعات مهمان خارجی (در صورت employee_id null)
            $table->string('external_full_name', 200)->nullable();
            $table->string('external_email', 200)->nullable();
            $table->string('external_mobile', 20)->nullable();
            $table->string('external_organization', 200)->nullable();
            $table->string('external_title', 200)->nullable();
            $table->string('external_national_code', 10)->nullable();

            // نقش در جلسه
            $table->enum('role', [
                'chairperson',     // رئیس
                'secretary',       // دبیر
                'presenter',       // ارائه‌دهنده
                'voting_member',   // عضو رأی‌دهنده
                'non_voting_member', // عضو بدون رأی
                'observer',        // ناظر
                'guest',           // مهمان
                'translator',      // مترجم
                'tech_support',    // پشتیبانی فنی
            ])->default('voting_member');

            $table->boolean('is_mandatory')->default(true)
                ->comment('آیا حضور این شخص الزامی است؟');
            $table->boolean('is_external')->default(false);

            // نظم نمایش
            $table->unsignedSmallInteger('order_index')->default(0);

            // وضعیت دعوت (هماهنگ با Invitation)
            $table->enum('invitation_status', [
                'not_invited',
                'invited',
                'accepted',
                'tentative',     // مشروط
                'declined',
                'no_response',
            ])->default('not_invited');
            $table->dateTime('invitation_responded_at')->nullable();
            $table->text('response_note')->nullable();

            // وضعیت حضور (بعد از جلسه)
            $table->enum('attendance_status', [
                'unknown',
                'present',
                'absent',
                'late',
                'left_early',
                'partial',         // بخشی از جلسه
                'remote',          // حضور آنلاین
            ])->default('unknown');
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->unsignedSmallInteger('attendance_minutes')->nullable();

            // اقدام به نمایندگی
            $table->foreignId('represented_by_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete()
                ->comment('در صورتی که فرد دیگری به نمایندگی حاضر شد');
            $table->foreignId('delegation_id')->nullable()
                ->constrained('user_delegations')->nullOnDelete();

            // امضای صورتجلسه (فاز ۳)
            $table->boolean('signed_minutes')->default(false);
            $table->dateTime('signed_at')->nullable();
            $table->string('signature_method', 50)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // یکتایی — هر کارمند یا (external) فقط یکبار در یک جلسه
            $table->unique(['meeting_id', 'employee_id'], 'mp_meeting_employee_unique');

            $table->index('meeting_id');
            $table->index('employee_id');
            $table->index('user_id');
            $table->index('invitation_status');
            $table->index('attendance_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
