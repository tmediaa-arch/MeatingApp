<?php

declare(strict_types=1);

namespace App\Domains\Exports\Generators;

use App\Domains\Exports\Contracts\ExportGeneratorInterface;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Meetings\Models\Meeting;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MeetingsExcelGenerator implements ExportGeneratorInterface
{
    public function supports(ExportJob $job): bool
    {
        return $job->export_type === ExportType::Meetings && $job->format === 'xlsx';
    }

    public function generate(ExportJob $job): array
    {
        $params = $job->input_params ?? [];

        $query = Meeting::query()
            ->when($job->organization_id, fn ($q, $id) => $q->where('organization_id', $id))
            ->when($params['date_from'] ?? null,
                fn ($q, $d) => $q->where('scheduled_start_at', '>=', $d))
            ->when($params['date_to'] ?? null,
                fn ($q, $d) => $q->where('scheduled_start_at', '<=', $d))
            ->when($params['status'] ?? null,
                fn ($q, $s) => $q->where('status', $s));

        $meetings = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('جلسات');
        $sheet->setRightToLeft(true);

        $headers = ['شماره', 'موضوع', 'وضعیت', 'شروع', 'پایان', 'سالن', 'میزبان'];
        foreach ($headers as $i => $h) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}1", $h);
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E40AF');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 2;
        foreach ($meetings as $meeting) {
            $sheet->setCellValue("A{$row}", $meeting->meeting_number);
            $sheet->setCellValue("B{$row}", $meeting->subject);
            $sheet->setCellValue("C{$row}", $meeting->status?->value ?? '');
            $sheet->setCellValue("D{$row}", $meeting->scheduled_start_at?->format('Y-m-d H:i'));
            $sheet->setCellValue("E{$row}", $meeting->scheduled_end_at?->format('Y-m-d H:i'));
            $sheet->setCellValue("F{$row}", $meeting->room?->name ?? '—');
            $sheet->setCellValue("G{$row}", $meeting->hostUser?->name ?? '—');
            $row++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mtg_xlsx_');
        (new Xlsx($spreadsheet))->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return [
            'content' => $content,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
            'filename' => 'meetings_' . now()->format('Ymd_His') . '.xlsx',
            'row_count' => $meetings->count(),
        ];
    }
}
