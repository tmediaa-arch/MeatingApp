<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    private array $settings = [
        // General
        ['key' => 'app.name', 'value' => 'سامانه مدیریت جلسات', 'type' => 'string', 'group' => 'general',
         'display_name' => 'نام سامانه', 'is_public' => true],
        ['key' => 'app.organization_name', 'value' => 'سازمان', 'type' => 'string', 'group' => 'general',
         'display_name' => 'نام سازمان', 'is_public' => true],
        ['key' => 'app.support_email', 'value' => 'support@example.com', 'type' => 'string', 'group' => 'general',
         'display_name' => 'ایمیل پشتیبانی'],
        ['key' => 'app.support_phone', 'value' => '021-12345678', 'type' => 'string', 'group' => 'general',
         'display_name' => 'تلفن پشتیبانی'],

        // Calendar
        ['key' => 'calendar.default_locale', 'value' => 'jalali', 'type' => 'string', 'group' => 'calendar',
         'display_name' => 'تقویم پیش‌فرض', 'options' => ['jalali' => 'شمسی', 'gregorian' => 'میلادی']],
        ['key' => 'calendar.working_hours_start', 'value' => '08:00', 'type' => 'string', 'group' => 'calendar',
         'display_name' => 'ساعت شروع کار'],
        ['key' => 'calendar.working_hours_end', 'value' => '17:00', 'type' => 'string', 'group' => 'calendar',
         'display_name' => 'ساعت پایان کار'],
        ['key' => 'calendar.working_days', 'value' => [1, 2, 3, 4, 5], 'type' => 'json', 'group' => 'calendar',
         'display_name' => 'روزهای کاری'],

        // Security
        ['key' => 'security.password_min_length', 'value' => 10, 'type' => 'integer', 'group' => 'security',
         'display_name' => 'حداقل طول رمز عبور'],
        ['key' => 'security.password_max_age_days', 'value' => 90, 'type' => 'integer', 'group' => 'security',
         'display_name' => 'حداکثر سن رمز عبور (روز)'],
        ['key' => 'security.lockout_threshold', 'value' => 5, 'type' => 'integer', 'group' => 'security',
         'display_name' => 'تعداد تلاش ناموفق برای قفل شدن'],
        ['key' => 'security.lockout_duration_minutes', 'value' => 15, 'type' => 'integer', 'group' => 'security',
         'display_name' => 'مدت قفل شدن (دقیقه)'],
        ['key' => 'security.session_lifetime_minutes', 'value' => 120, 'type' => 'integer', 'group' => 'security',
         'display_name' => 'مدت اعتبار session (دقیقه)'],
        ['key' => 'security.require_mfa_for_admins', 'value' => true, 'type' => 'boolean', 'group' => 'security',
         'display_name' => 'الزام MFA برای ادمین‌ها'],

        // Notifications
        ['key' => 'notifications.email_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'notifications',
         'display_name' => 'فعال بودن ایمیل'],
        ['key' => 'notifications.sms_enabled', 'value' => false, 'type' => 'boolean', 'group' => 'notifications',
         'display_name' => 'فعال بودن پیامک'],
        ['key' => 'notifications.sms_gateway', 'value' => null, 'type' => 'string', 'group' => 'notifications',
         'display_name' => 'Gateway پیامک'],
        ['key' => 'notifications.sms_api_key', 'value' => null, 'type' => 'secret', 'group' => 'notifications',
         'display_name' => 'کلید API پیامک', 'is_encrypted' => true],
        ['key' => 'notifications.default_quiet_hours_start', 'value' => '22:00', 'type' => 'string', 'group' => 'notifications',
         'display_name' => 'شروع پیش‌فرض ساعات بی‌مزاحمت'],
        ['key' => 'notifications.default_quiet_hours_end', 'value' => '07:00', 'type' => 'string', 'group' => 'notifications',
         'display_name' => 'پایان پیش‌فرض ساعات بی‌مزاحمت'],

        // Files
        ['key' => 'files.max_upload_size_mb', 'value' => 50, 'type' => 'integer', 'group' => 'files',
         'display_name' => 'حداکثر حجم فایل (MB)'],
        ['key' => 'files.antivirus_enabled', 'value' => false, 'type' => 'boolean', 'group' => 'files',
         'display_name' => 'اسکن ضدبدافزار'],

        // Audit
        ['key' => 'audit.retention_days', 'value' => 730, 'type' => 'integer', 'group' => 'audit',
         'display_name' => 'مدت نگهداری لاگ (روز)'],

        // Branding
        ['key' => 'branding.primary_color', 'value' => '#3b82f6', 'type' => 'string', 'group' => 'branding',
         'display_name' => 'رنگ اصلی', 'is_public' => true],
        ['key' => 'branding.logo_path', 'value' => null, 'type' => 'file', 'group' => 'branding',
         'display_name' => 'لوگو', 'is_public' => true],
    ];

    public function run(): void
    {
        $order = 0;

        DB::transaction(function () use (&$order) {
            foreach ($this->settings as $setting) {
                $value = $setting['value'] ?? null;

                // مقادیر JSON باید encode شوند
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                } elseif (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value !== null) {
                    $value = (string) $value;
                }

                // برای secret: encrypt
                if (($setting['type'] ?? 'string') === 'secret' && $value !== null) {
                    $value = encrypt($value);
                }

                $options = $setting['options'] ?? null;
                if (is_array($options)) {
                    $options = json_encode($options, JSON_UNESCAPED_UNICODE);
                }

                DB::table('settings')->updateOrInsert(
                    ['key' => $setting['key']],
                    [
                        'value' => $value,
                        'type' => $setting['type'] ?? 'string',
                        'group' => $setting['group'] ?? 'general',
                        'subgroup' => $setting['subgroup'] ?? null,
                        'display_name' => $setting['display_name'] ?? $setting['key'],
                        'description' => $setting['description'] ?? null,
                        'help_text' => $setting['help_text'] ?? null,
                        'validation_rules' => isset($setting['validation_rules'])
                            ? json_encode($setting['validation_rules']) : null,
                        'options' => $options,
                        'is_public' => $setting['is_public'] ?? false,
                        'is_encrypted' => $setting['is_encrypted'] ?? false,
                        'is_system' => true,
                        'is_readonly' => false,
                        'display_order' => $order++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
