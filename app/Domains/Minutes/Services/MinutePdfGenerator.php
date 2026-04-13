<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Services;

use App\Domains\Calendar\Services\JalaliCalendarService;
use App\Domains\Minutes\Models\Minute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * تولید PDF صورتجلسه با RTL و فونت فارسی.
 *
 * در این فاز، خروجی HTML→PDF با dompdf/wkhtmltopdf انجام می‌شود.
 * Template در resources/views/pdf/minute.blade.php است.
 *
 * در فاز ۶ (Reports) با نسخه پیشرفته‌تر جایگزین می‌شود.
 */
class MinutePdfGenerator
{
    public function __construct(
        private readonly JalaliCalendarService $jalaliService,
    ) {
    }

    /**
     * تولید PDF و ذخیره در storage. مسیر و hash برمی‌گرداند.
     *
     * @return array{path: string, hash: string, size: int}
     */
    public function generate(Minute $minute): array
    {
        $html = $this->renderHtml($minute);

        // در فاز ۳ از dompdf استفاده می‌کنیم. اگر در محیط ساخته نشده، fallback به HTML.
        // (نصب: composer require barryvdh/laravel-dompdf)
        $pdfBinary = $this->htmlToPdf($html);

        $fileName = sprintf(
            'minutes/%s/%s-v%d.pdf',
            $minute->organization_id,
            $minute->minute_number,
            $minute->current_version,
        );

        Storage::disk('local')->put($fileName, $pdfBinary);

        $hash = hash('sha256', $pdfBinary);
        $size = strlen($pdfBinary);

        return [
            'path' => $fileName,
            'hash' => $hash,
            'size' => $size,
        ];
    }

    private function renderHtml(Minute $minute): string
    {
        $minute->load([
            'meeting.chairperson',
            'meeting.secretary',
            'meeting.room',
            'meeting.participants.employee',
            'meeting.agendaItemsRelation',
            'secretary',
            'chairperson',
            'signatures.signer',
            'resolutions',
        ]);

        // اگر view وجود ندارد، fallback به HTML inline
        if (View::exists('pdf.minute')) {
            return view('pdf.minute', [
                'minute' => $minute,
                'meeting' => $minute->meeting,
                'jalali' => $this->jalaliService,
            ])->render();
        }

        // Fallback HTML — basic
        return $this->generateFallbackHtml($minute);
    }

    private function generateFallbackHtml(Minute $minute): string
    {
        $meeting = $minute->meeting;
        $jalali = $this->jalaliService;

        $participantsRows = '';
        foreach ($meeting->participants as $p) {
            $participantsRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($p->display_name),
                e($p->role->label()),
                e($p->attendance_status?->label() ?? '—'),
            );
        }

        $signaturesHtml = '';
        foreach ($minute->signatures as $sig) {
            $signaturesHtml .= sprintf(
                '<div style="margin: 20px 0;"><strong>%s:</strong> %s — %s</div>',
                e($sig->signer_role === 'secretary' ? 'دبیر' : 'رئیس'),
                e($sig->signer->name),
                e($jalali->formatDateTime($sig->signed_at)),
            );
        }

        $resolutionsHtml = '';
        foreach ($minute->resolutions as $r) {
            $resolutionsHtml .= sprintf(
                '<div style="margin: 15px 0; padding: 10px; border-right: 4px solid #4f46e5;">
                    <h3>%s — %s</h3>
                    <div>%s</div>
                </div>',
                e($r->resolution_number),
                e($r->title),
                $r->content, // already HTML
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<title>{$minute->minute_number}</title>
<style>
body { font-family: 'IranSans', Tahoma, sans-serif; direction: rtl; padding: 30px; }
h1 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
.header { text-align: center; margin-bottom: 20px; }
.meta { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
.meta div { margin: 5px 0; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
th { background: #f0f0f0; }
.signatures { margin-top: 40px; padding-top: 20px; border-top: 1px solid #333; }
.content { line-height: 1.8; margin: 30px 0; }
</style>
</head>
<body>
<div class="header">
    <h1>صورتجلسه</h1>
    <h2>{$minute->title}</h2>
    <div>شماره صورتجلسه: <strong>{$minute->minute_number}</strong></div>
</div>

<div class="meta">
    <div><strong>موضوع جلسه:</strong> {$meeting->subject}</div>
    <div><strong>تاریخ:</strong> {$jalali->formatHuman($meeting->scheduled_start_at)}</div>
    <div><strong>محل:</strong> {$this->getLocationText($meeting)}</div>
    <div><strong>رئیس جلسه:</strong> {$meeting->chairperson?->full_name}</div>
    <div><strong>دبیر:</strong> {$meeting->secretary?->full_name}</div>
</div>

<h2>شرکت‌کنندگان</h2>
<table>
    <thead><tr><th>نام</th><th>نقش</th><th>حضور</th></tr></thead>
    <tbody>{$participantsRows}</tbody>
</table>

<h2>متن صورتجلسه</h2>
<div class="content">{$minute->content_html}</div>

<h2>مصوبات</h2>
{$resolutionsHtml}

<div class="signatures">
    <h2>امضاها</h2>
    {$signaturesHtml}
</div>

<div style="text-align: center; margin-top: 50px; font-size: 0.85em; color: #666;">
    تولید شده در: {$jalali->formatDateTime(now())} —
    Hash: {$minute->pdf_hash}
</div>
</body>
</html>
HTML;
    }

    private function getLocationText($meeting): string
    {
        if ($meeting->room) {
            return $meeting->room->full_location ?? $meeting->room->name;
        }
        return $meeting->location_alt ?? '—';
    }

    /**
     * HTML → PDF
     * در محیط واقعی از dompdf/wkhtmltopdf استفاده می‌شود.
     */
    private function htmlToPdf(string $html): string
    {
        // اگر dompdf نصب باشد:
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false)
                ->output();
        }

        // Fallback — در حال حاضر HTML را به‌عنوان PDF placeholder ذخیره می‌کنیم
        // در production این مسیر نباید اجرا شود
        return $html;
    }
}
