<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Minutes\Models\MinuteVersion;
use Illuminate\Support\Facades\DB;

class CreateMinuteAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(Meeting $meeting, array $data = []): Minute
    {
        // 1. اعتبارسنجی: فقط جلسات Completed یا InProgress
        if (!in_array($meeting->status, [
            MeetingStatus::Completed,
            MeetingStatus::InProgress,
        ], true)) {
            throw MinuteException::meetingNotCompleted();
        }

        // 2. duplicate check
        if ($meeting->minute()->exists()) {
            throw MinuteException::alreadyExists();
        }

        return DB::transaction(function () use ($meeting, $data) {
            // 3. تولید شماره صورتجلسه
            $minuteNumber = $this->generateMinuteNumber($meeting->organization_id);

            // 4. ایجاد minute
            $minute = Minute::create([
                'meeting_id' => $meeting->id,
                'organization_id' => $meeting->organization_id,
                'minute_number' => $minuteNumber,
                'title' => $data['title'] ?? sprintf('صورتجلسه %s', $meeting->subject),
                'content_html' => $data['content_html'] ?? $this->generateInitialContent($meeting),
                'content_text' => $data['content_text'] ?? null,
                'summary' => $data['summary'] ?? null,
                'key_decisions' => $data['key_decisions'] ?? null,
                'status' => MinuteStatus::Draft,
                'secretary_employee_id' => $meeting->secretary_employee_id,
                'chairperson_employee_id' => $meeting->chairperson_employee_id,
                'current_version' => 1,
                'confidentiality_level' => $meeting->confidentiality_level,
                'creator_user_id' => auth()->id() ?? $data['creator_user_id'],
            ]);

            // 5. اولین نسخه (snapshot)
            MinuteVersion::create([
                'minute_id' => $minute->id,
                'version_number' => 1,
                'content_html' => $minute->content_html,
                'content_text' => $minute->content_text,
                'change_summary' => 'نسخه اولیه',
                'created_by_user_id' => auth()->id() ?? $data['creator_user_id'],
                'created_at' => now(),
            ]);

            // 6. audit
            $this->auditService->log(
                event: 'minute_created',
                auditable: $minute,
                description: sprintf(
                    "صورتجلسه '%s' برای جلسه '%s' ایجاد شد",
                    $minute->minute_number,
                    $meeting->meeting_number,
                ),
                context: [
                    'meeting_id' => $meeting->id,
                    'meeting_number' => $meeting->meeting_number,
                ],
                severity: 'notice',
            );

            return $minute;
        });
    }

    private function generateMinuteNumber(int $organizationId): string
    {
        $orgCode = \App\Domains\Organization\Models\Organization::find($organizationId)->code ?? 'ORG';
        $year = now()->year;

        // آخرین minute_number برای این سال و سازمان
        $prefix = "{$orgCode}-MIN-{$year}-";
        $last = Minute::where('organization_id', $organizationId)
            ->where('minute_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('minute_number');

        if ($last) {
            $lastNum = (int) substr($last, strrpos($last, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s%04d', $prefix, $nextNum);
    }

    /**
     * تولید محتوای اولیه براساس متادیتای جلسه
     */
    private function generateInitialContent(Meeting $meeting): string
    {
        $jalali = app(\App\Domains\Calendar\Services\JalaliCalendarService::class);

        $startStr = $jalali->formatHuman($meeting->scheduled_start_at);
        $chairperson = $meeting->chairperson?->full_name ?? '—';
        $secretary = $meeting->secretary?->full_name ?? '—';

        $agendaItemsHtml = '<ul>';
        foreach ($meeting->agendaItemsRelation as $item) {
            $agendaItemsHtml .= '<li>' . e($item->title) . '</li>';
        }
        $agendaItemsHtml .= '</ul>';

        return <<<HTML
<p>این صورتجلسه مربوط به جلسه <strong>{$meeting->subject}</strong> به شماره <strong>{$meeting->meeting_number}</strong> است که در تاریخ <strong>{$startStr}</strong> برگزار شد.</p>

<h3>افراد کلیدی:</h3>
<ul>
    <li>رئیس جلسه: {$chairperson}</li>
    <li>دبیر جلسه: {$secretary}</li>
</ul>

<h3>دستور جلسه:</h3>
{$agendaItemsHtml}

<h3>متن مذاکرات و تصمیمات:</h3>
<p>[متن صورتجلسه را اینجا وارد کنید...]</p>

<h3>مصوبات:</h3>
<p>[مصوبات جلسه را اینجا فهرست کنید...]</p>
HTML;
    }
}
