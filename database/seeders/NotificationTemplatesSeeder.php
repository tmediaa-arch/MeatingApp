<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Models\NotificationTemplate;
use App\Domains\Notifications\Models\NotificationTemplateChannel;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ──── Meetings ────
            [
                'key' => 'meeting.invitation',
                'name' => 'دعوت به جلسه',
                'category' => 'meeting',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'دعوت به جلسه "{{ meeting_subject }}"',
                        'body' => "سلام {{ user_name }} عزیز،\n\nشما به جلسه «{{ meeting_subject }}» در تاریخ {{ meeting_date }} ساعت {{ meeting_time }} دعوت شده‌اید.\nمکان: {{ meeting_location }}\n\n{{ app_name }}",
                    ],
                    NotificationChannel::Sms->value => [
                        'body' => 'دعوت به جلسه: {{ meeting_subject }} - {{ meeting_date }} ساعت {{ meeting_time }}',
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => 'دعوت به جلسه جدید',
                        'body' => '{{ meeting_subject }} - {{ meeting_date }} ساعت {{ meeting_time }}',
                    ],
                ],
            ],
            [
                'key' => 'meeting.reminder',
                'name' => 'یادآور جلسه',
                'category' => 'meeting',
                'default_priority' => 'high',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'یادآور جلسه — {{ meeting_subject }}',
                        'body' => 'جلسه شما در {{ time_until }} برگزار می‌شود.',
                    ],
                    NotificationChannel::Sms->value => [
                        'body' => 'یادآور: جلسه «{{ meeting_subject }}» در {{ time_until }}',
                    ],
                ],
            ],
            [
                'key' => 'meeting.cancelled',
                'name' => 'لغو جلسه',
                'category' => 'meeting',
                'default_priority' => 'high',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'لغو جلسه «{{ meeting_subject }}»',
                        'body' => "جلسه «{{ meeting_subject }}» در تاریخ {{ meeting_date }} لغو شد.\nعلت: {{ cancellation_reason }}",
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => 'لغو جلسه',
                        'body' => '«{{ meeting_subject }}» لغو شد.',
                    ],
                ],
            ],
            [
                'key' => 'meeting.rescheduled',
                'name' => 'تغییر زمان جلسه',
                'category' => 'meeting',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'تغییر زمان جلسه «{{ meeting_subject }}»',
                        'body' => 'زمان جلسه از {{ old_time }} به {{ new_time }} تغییر کرد.',
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => 'تغییر زمان جلسه',
                        'body' => '«{{ meeting_subject }}» به {{ new_time }} تغییر کرد.',
                    ],
                ],
            ],
            // ──── Minutes ────
            [
                'key' => 'minute.review_requested',
                'name' => 'درخواست بازبینی صورتجلسه',
                'category' => 'minute',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'بازبینی صورتجلسه — {{ minute_number }}',
                        'body' => 'لطفاً صورتجلسه «{{ minute_title }}» را بازبینی کنید.',
                    ],
                ],
            ],
            [
                'key' => 'minute.signed',
                'name' => 'امضای صورتجلسه',
                'category' => 'minute',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'صورتجلسه امضا شد',
                        'body' => 'صورتجلسه «{{ minute_number }}» توسط {{ signer_name }} امضا شد.',
                    ],
                ],
            ],
            [
                'key' => 'minute.published',
                'name' => 'انتشار صورتجلسه',
                'category' => 'minute',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'صورتجلسه «{{ minute_number }}» منتشر شد',
                        'body' => "صورتجلسه «{{ minute_title }}» مربوط به جلسه {{ meeting_number }} ({{ meeting_subject }}) منتشر شد و در سامانه قابل مشاهده است.",
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => 'صورتجلسه منتشر شد',
                        'body' => '«{{ minute_title }}» منتشر شد.',
                    ],
                ],
            ],
            // ──── Resolutions ────
            [
                'key' => 'resolution.created',
                'name' => 'ایجاد مصوبه',
                'category' => 'resolution',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'مصوبه جدید — {{ resolution_number }}',
                        'body' => '{{ resolution_title }}',
                    ],
                ],
            ],
            [
                'key' => 'resolution.voting_opened',
                'name' => 'شروع رأی‌گیری',
                'category' => 'resolution',
                'default_priority' => 'high',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'رأی‌گیری شروع شد',
                        'body' => 'برای مصوبه «{{ resolution_title }}» رأی خود را ثبت کنید.',
                    ],
                    NotificationChannel::Email->value => [
                        'subject' => 'رأی‌گیری — {{ resolution_number }}',
                        'body' => 'رأی‌گیری برای مصوبه «{{ resolution_title }}» باز است. لطفاً رأی خود را ثبت کنید.',
                    ],
                ],
            ],
            // ──── Tasks ────
            [
                'key' => 'task.assigned',
                'name' => 'ارجاع وظیفه',
                'category' => 'task',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'وظیفه جدید — {{ task_number }}',
                        'body' => "وظیفه «{{ task_title }}» به شما ارجاع داده شد.\nمهلت: {{ due_date }}",
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => 'وظیفه جدید',
                        'body' => '{{ task_title }} (مهلت: {{ due_date }})',
                    ],
                    NotificationChannel::Sms->value => [
                        'body' => 'وظیفه جدید: {{ task_title }} - مهلت {{ due_date }}',
                    ],
                ],
            ],
            [
                'key' => 'task.submitted',
                'name' => 'ارسال وظیفه',
                'category' => 'task',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'وظیفه برای تأیید ارسال شد',
                        'body' => '{{ submitter_name }} وظیفه «{{ task_title }}» را ارسال کرد.',
                    ],
                ],
            ],
            [
                'key' => 'task.due_soon',
                'name' => 'یادآور نزدیک شدن مهلت',
                'category' => 'task',
                'default_priority' => 'high',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'یادآور مهلت وظیفه',
                        'body' => 'مهلت وظیفه «{{ task_title }}» در {{ days_left }} روز.',
                    ],
                    NotificationChannel::Sms->value => [
                        'body' => 'یادآور: {{ task_title }} - {{ days_left }} روز مانده',
                    ],
                ],
            ],
            [
                'key' => 'task.overdue',
                'name' => 'وظیفه تأخیردار',
                'category' => 'task',
                'default_priority' => 'critical',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'وظیفه تأخیردار — {{ task_number }}',
                        'body' => 'وظیفه «{{ task_title }}» تأخیر دارد. لطفاً اقدام کنید.',
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => '⚠️ تأخیر وظیفه',
                        'body' => '{{ task_title }} {{ days_overdue }} روز تأخیر دارد.',
                    ],
                    NotificationChannel::Sms->value => [
                        'body' => 'تأخیر: {{ task_title }} - {{ days_overdue }} روز',
                    ],
                ],
            ],
            [
                'key' => 'task.escalated',
                'name' => 'Escalation وظیفه',
                'category' => 'task',
                'default_priority' => 'critical',
                'channels' => [
                    NotificationChannel::Email->value => [
                        'subject' => 'Escalation سطح {{ level }} — {{ task_number }}',
                        'body' => "وظیفه «{{ task_title }}» {{ days_overdue }} روز تأخیر دارد و به سطح {{ level }} رسیده است. لطفاً مداخله کنید.",
                    ],
                    NotificationChannel::InApp->value => [
                        'subject' => '🚨 Escalation سطح {{ level }}',
                        'body' => '{{ task_title }} - {{ days_overdue }} روز تأخیر',
                    ],
                ],
            ],
            [
                'key' => 'task.extension_requested',
                'name' => 'درخواست تمدید',
                'category' => 'task',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'درخواست تمدید — {{ task_number }}',
                        'body' => '{{ requester_name }} برای وظیفه «{{ task_title }}» تمدید تا {{ new_due_date }} درخواست داد. علت: {{ reason }}',
                    ],
                ],
            ],
            [
                'key' => 'task.extension_reviewed',
                'name' => 'بررسی درخواست تمدید',
                'category' => 'task',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'تمدید {{ status }} شد',
                        'body' => 'درخواست تمدید برای «{{ task_number }}» توسط {{ reviewer_name }} {{ status }} شد. {{ note }}',
                    ],
                ],
            ],
            [
                'key' => 'task.completed',
                'name' => 'تکمیل وظیفه',
                'category' => 'task',
                'channels' => [
                    NotificationChannel::InApp->value => [
                        'subject' => 'وظیفه تکمیل شد',
                        'body' => '«{{ task_title }}» با کیفیت "{{ quality }}" تأیید و تکمیل شد.',
                    ],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            $channels = $tpl['channels'];
            unset($tpl['channels']);

            // mapping from seeder shorthand to actual columns
            $displayName = $tpl['name'] ?? $tpl['key'];
            $priority = $tpl['default_priority'] ?? 'normal';
            unset($tpl['name'], $tpl['default_priority'], $tpl['category']);

            $template = NotificationTemplate::updateOrCreate(
                ['organization_id' => null, 'key' => $tpl['key']],
                array_merge($tpl, [
                    'display_name' => $displayName,
                    'priority' => $priority,
                    'supported_channels' => array_keys($channels),
                    'is_active' => true,
                    'is_admin_editable' => true,
                    'is_user_disablable' => true,
                ]),
            );

            foreach ($channels as $channel => $content) {
                NotificationTemplateChannel::updateOrCreate(
                    ['template_id' => $template->id, 'channel' => $channel],
                    [
                        'subject' => $content['subject'] ?? null,
                        'body' => $content['body'],
                        'body_html' => $content['body_html'] ?? null,
                    ],
                );
            }
        }

        $this->command->info(sprintf('%d قالب اعلان seed شد.', count($templates)));
    }
}
