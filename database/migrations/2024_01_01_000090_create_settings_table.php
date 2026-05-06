<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings Domain — Runtime settings
 *
 * تنظیمات سامانه که توسط ادمین قابل تغییرند بدون redeploy.
 *
 * طراحی key-value generic:
 * - key یکتا (مثلاً "notifications.email.enabled")
 * - value به‌صورت JSON ذخیره می‌شود (string|bool|int|array|object)
 * - type برای validation: string|integer|boolean|json|file|secret
 * - secret یعنی encrypted (مثل API key های SMS Gateway)
 *
 * group برای دسته‌بندی UI:
 *   general, calendar, notifications, security, integrations, branding, ...
 *
 * is_public یعنی می‌توان آن را به frontend هم فرستاد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key', 150)->unique();
            $table->longText('value')->nullable()->comment('JSON encoded — for secret values: encrypted');

            $table->string('type', 30)->default('string')
                ->comment('string|integer|boolean|float|json|file|secret');
            $table->string('group', 50)->default('general')->index();
            $table->string('subgroup', 50)->nullable();

            // متادیتای UI
            $table->string('display_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('help_text', 500)->nullable();
            $table->json('validation_rules')->nullable()
                ->comment('Laravel validation rules array');
            $table->json('options')->nullable()
                ->comment('برای select: لیست گزینه‌ها');

            $table->boolean('is_public')->default(false)
                ->comment('قابل ارسال به frontend بدون authentication');
            $table->boolean('is_encrypted')->default(false)
                ->comment('مقدار به‌صورت رمزنگاری شده ذخیره شده');
            $table->boolean('is_system')->default(false)
                ->comment('سیستمی — قابل حذف نیست');
            $table->boolean('is_readonly')->default(false)
                ->comment('فقط در migration/seeder قابل تغییر');

            $table->unsignedInteger('display_order')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group', 'subgroup', 'display_order']);
        });

        // notification_preferences برای هر کاربر
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('notification_type', 100)
                ->comment('کد نوع اعلان: meeting_invitation|task_assigned|...');

            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('database_enabled')->default(true);
            $table->boolean('websocket_enabled')->default(true);

            // ساعات بی‌مزاحمت (در منطقه زمانی کاربر)
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            // برای حالت‌های فوری override می‌شود
            $table->boolean('respect_quiet_hours_for_urgent')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('settings');
    }
};
