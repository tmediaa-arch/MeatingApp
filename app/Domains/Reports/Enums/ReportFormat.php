<?php

declare(strict_types=1);

namespace App\Domains\Reports\Enums;

enum ReportFormat: string
{
    case Html = 'html';
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Html => 'HTML',
            self::Pdf => 'PDF',
            self::Xlsx => 'Excel',
            self::Csv => 'CSV',
            self::Json => 'JSON',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Html => 'text/html',
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv',
            self::Json => 'application/json',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Html => 'html',
            self::Pdf => 'pdf',
            self::Xlsx => 'xlsx',
            self::Csv => 'csv',
            self::Json => 'json',
        };
    }
}
