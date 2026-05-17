<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee Position History & User Delegations
 *
 * employee_position_histories:
 *   هر بار که یک کارمند به پست جدیدی منصوب می‌شود یا از پستی برکنار می‌شود،
 *   یک رکورد جدید اضافه می‌شود. این جدول AppendOnly از طریق Observer enforce می‌شود.
 *   ended_at = null یعنی این انتساب فعلاً جاری است.
 *
 * user_delegations:
 *   تفویض اختیار موقت — یکی از مهم‌ترین مفاهیم سامانه‌های اداری ایرانی.
 *   یک کاربر در دوره مرخصی یا مأموریت، اختیارات خود را به کاربر دیگری تفویض می‌کند.
 *   در این بازه، delegate_user_id می‌تواند به‌جای delegator_user_id اقدام کند.
 *   scope تعیین می‌کند چه چیزهایی تفویض شده (همه/جلسات/امضا/تأیید مصوبات).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_position_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();

            // نوع رابطه با پست
            $table->string('assignment_type', 30)->default('primary')
                ->comment('primary|secondary|acting|substitute|delegated');

            // بازه زمانی انتساب
            $table->date('started_at');
            $table->date('ended_at')->nullable()->index();

            // در صورت پایان یافتن، دلیل
            $table->string('end_reason', 50)->nullable()
                ->comment('promotion|transfer|resignation|retirement|termination|reorganization');

            // شناسه حکم/مدرک رسمی
            $table->string('decree_number', 50)->nullable();
            $table->date('decree_date')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            // عمداً soft delete نمی‌گذاریم — این append-only است

            $table->index(['employee_id', 'started_at']);
            $table->index(['position_id', 'ended_at']);
            // اطمینان از اینکه برای یک employee+position+started_at یکتا باشد
            $table->unique(['employee_id', 'position_id', 'started_at'], 'eph_emp_pos_started_unique');
        });

        Schema::create('user_delegations', function (Blueprint $table) {
            $table->id();

            // تفویض‌کننده و گیرنده
            $table->foreignId('delegator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();

            // بازه اعتبار
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();

            // دامنه تفویض
            $table->string('scope', 50)->default('all')->index()
                ->comment('all|meetings|signatures|approvals|tasks|inbox');

            // محدودسازی به موجودیت‌های خاص (مثلاً فقط جلسه با id=42)
            $table->json('restricted_to')->nullable()
                ->comment('JSON با ساختار {meetings: [ids], roles: [...]}');

            // دلیل تفویض (مرخصی، مأموریت، ...)
            $table->string('reason', 100)->nullable()
                ->comment('leave|mission|illness|temporary_absence|other');
            $table->text('reason_description')->nullable();

            // وضعیت
            $table->string('status', 20)->default('active')->index()
                ->comment('pending|active|expired|revoked|completed');

            // شماره حکم/مدرک
            $table->string('decree_number', 50)->nullable();
            $table->date('decree_date')->nullable();

            // ردیابی استفاده
            $table->unsignedInteger('actions_count')->default(0)
                ->comment('تعداد دفعاتی که delegate از این تفویض استفاده کرده');
            $table->timestamp('last_used_at')->nullable();

            // ردیابی
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['delegator_user_id', 'status']);
            $table->index(['delegate_user_id', 'status']);
            $table->index(['starts_at', 'ends_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_delegations');
        Schema::dropIfExists('employee_position_histories');
    }
};
