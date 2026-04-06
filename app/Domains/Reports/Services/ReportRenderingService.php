<?php

declare(strict_types=1);

namespace App\Domains\Reports\Services;

use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\Report;
use App\Domains\Reports\Models\ReportRun;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ReportRenderingService — تبدیل ReportResult به فرمت‌های مختلف.
 *
 * این سرویس bytestream فایل را برمی‌گرداند. ذخیره روی disk
 * و ساخت File record توسط ExportFileAction انجام می‌شود.
 */
class ReportRenderingService
{
    /**
     * @return array{content: string, mime: string, extension: string, filename: string}
     */
    public function render(Report $report, ReportRun $run, ReportFormat $format): array
    {
        $result = ReportResult::class === gettype($run->result_data)
            ? $run->result_data
            : new ReportResult(
                rows: $run->result_data['rows'] ?? [],
                columns: $run->result_data['columns'] ?? [],
                summary: $run->result_data['summary'] ?? [],
                charts: $run->result_data['charts'] ?? [],
                meta: $run->result_data['meta'] ?? [],
            );

        $filename = $this->buildFilename($report, $format);

        $content = match ($format) {
            ReportFormat::Html => $this->renderHtml($report, $result),
            ReportFormat::Pdf => $this->renderPdf($report, $result),
            ReportFormat::Xlsx => $this->renderXlsx($report, $result),
            ReportFormat::Csv => $this->renderCsv($result),
            ReportFormat::Json => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        };

        return [
            'content' => $content,
            'mime' => $format->mimeType(),
            'extension' => $format->extension(),
            'filename' => $filename,
        ];
    }

    private function buildFilename(Report $report, ReportFormat $format): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $report->key);
        return sprintf('%s_%s.%s', $safe, now()->format('Ymd_His'), $format->extension());
    }

    // ──────── HTML ────────
    private function renderHtml(Report $report, ReportResult $result): string
    {
        if (view()->exists('reports.render-html')) {
            return view('reports.render-html', [
                'report' => $report,
                'result' => $result,
            ])->render();
        }

        return $this->fallbackHtml($report, $result);
    }

    private function fallbackHtml(Report $report, ReportResult $result): string
    {
        $html = '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:Tahoma,sans-serif}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:6px;text-align:right}th{background:#f3f4f6}h1{color:#1e40af}</style>';
        $html .= '</head><body>';
        $html .= "<h1>{$report->display_name}</h1>";

        if ($result->summary) {
            $html .= '<h3>خلاصه</h3><ul>';
            foreach ($result->summary as $k => $v) {
                if (is_scalar($v)) {
                    $html .= "<li><strong>{$k}:</strong> " . htmlspecialchars((string) $v) . '</li>';
                }
            }
            $html .= '</ul>';
        }

        if ($result->rows && $result->columns) {
            $html .= '<h3>داده‌ها</h3><table><thead><tr>';
            foreach ($result->columns as $col) {
                $html .= '<th>' . htmlspecialchars($col['label']) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($result->rows as $row) {
                $row = (array) $row;
                $html .= '<tr>';
                foreach ($result->columns as $col) {
                    $val = $row[$col['key']] ?? '';
                    $html .= '<td>' . htmlspecialchars((string) (is_scalar($val) ? $val : json_encode($val))) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<hr><p style="color:#666;font-size:10px">تولید شده در ' . now()->format('Y-m-d H:i') . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    // ──────── PDF ────────
    private function renderPdf(Report $report, ReportResult $result): string
    {
        $html = $this->renderHtml($report, $result);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4');
            return $pdf->output();
        }

        // fallback: html محتوا با ضمیمه minimal pdf wrapper
        // (در production همیشه dompdf نصب است)
        return $html;
    }

    // ──────── XLSX ────────
    private function renderXlsx(Report $report, ReportResult $result): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('گزارش');

        // RTL برای فارسی
        $sheet->setRightToLeft(true);

        // عنوان
        $sheet->setCellValue('A1', $report->display_name);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $currentRow = 3;

        // خلاصه
        if ($result->summary) {
            $sheet->setCellValue("A{$currentRow}", 'خلاصه:');
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
            $currentRow++;

            foreach ($result->summary as $k => $v) {
                if (is_scalar($v)) {
                    $sheet->setCellValue("A{$currentRow}", $k);
                    $sheet->setCellValue("B{$currentRow}", (string) $v);
                    $currentRow++;
                }
            }
            $currentRow++;
        }

        // داده‌ها
        if ($result->rows && $result->columns) {
            $col = 'A';
            foreach ($result->columns as $column) {
                $sheet->setCellValue("{$col}{$currentRow}", $column['label']);
                $col++;
            }

            // استایل header
            $lastCol = chr(ord('A') + count($result->columns) - 1);
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFont()->setBold(true);
            $currentRow++;

            foreach ($result->rows as $row) {
                $row = (array) $row;
                $col = 'A';
                foreach ($result->columns as $column) {
                    $val = $row[$column['key']] ?? '';
                    $sheet->setCellValue("{$col}{$currentRow}", is_scalar($val) ? $val : json_encode($val));
                    $col++;
                }
                $currentRow++;
            }

            // border کلی
            $sheet->getStyle("A1:{$lastCol}" . ($currentRow - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);
        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $content;
    }

    // ──────── CSV ────────
    private function renderCsv(ReportResult $result): string
    {
        $csv = Writer::createFromString();
        $csv->setOutputBOM(Writer::BOM_UTF8);

        if ($result->columns) {
            $csv->insertOne(array_column($result->columns, 'label'));

            foreach ($result->rows as $row) {
                $row = (array) $row;
                $line = [];
                foreach ($result->columns as $column) {
                    $val = $row[$column['key']] ?? '';
                    $line[] = is_scalar($val) ? (string) $val : json_encode($val);
                }
                $csv->insertOne($line);
            }
        }

        return $csv->toString();
    }
}
