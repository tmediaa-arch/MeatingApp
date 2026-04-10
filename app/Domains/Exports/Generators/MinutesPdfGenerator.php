<?php

declare(strict_types=1);

namespace App\Domains\Exports\Generators;

use App\Domains\Exports\Contracts\ExportGeneratorInterface;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Minutes\Models\Minute;
use Barryvdh\DomPDF\Facade\Pdf;

class MinutesPdfGenerator implements ExportGeneratorInterface
{
    public function supports(ExportJob $job): bool
    {
        return $job->export_type === ExportType::Minutes && $job->format === 'pdf';
    }

    public function generate(ExportJob $job): array
    {
        $params = $job->input_params ?? [];
        $minuteId = $params['minute_id'] ?? null;

        if (!$minuteId) {
            throw new \InvalidArgumentException('minute_id الزامی است.');
        }

        $minute = Minute::with(['meeting', 'currentVersion'])->findOrFail($minuteId);

        $html = $this->buildHtml($minute);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4');

        return [
            'content' => $pdf->output(),
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'filename' => sprintf('minute_%s_%s.pdf', $minute->minute_number, now()->format('Ymd')),
            'row_count' => 1,
        ];
    }

    private function buildHtml(Minute $minute): string
    {
        $title = htmlspecialchars($minute->title);
        $number = htmlspecialchars($minute->minute_number);
        $content = $minute->currentVersion?->content_html ?? $minute->content_html ?? '';
        $meetingSubject = $minute->meeting?->subject
            ? '<p><strong>جلسه مرتبط:</strong> ' . htmlspecialchars($minute->meeting->subject) . '</p>'
            : '';
        $published = $minute->published_at?->format('Y-m-d') ?? '—';

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Tahoma, sans-serif; padding: 30px; }
    h1 { color: #1e40af; text-align: center; border-bottom: 2px solid #1e40af; padding-bottom: 10px; }
    .meta { background: #f3f4f6; padding: 15px; margin: 20px 0; border-radius: 6px; }
    .content { line-height: 1.8; text-align: justify; }
    .footer { text-align: center; color: #6b7280; font-size: 11px; margin-top: 30px; }
</style>
</head>
<body>
    <h1>{$title}</h1>
    <div class="meta">
        <p><strong>شماره صورتجلسه:</strong> {$number}</p>
        <p><strong>تاریخ انتشار:</strong> {$published}</p>
        {$meetingSubject}
    </div>
    <div class="content">{$content}</div>
    <div class="footer">تولید شده توسط سامانه MMS — {$published}</div>
</body>
</html>
HTML;
    }
}
