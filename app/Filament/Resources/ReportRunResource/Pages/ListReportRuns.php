<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportRunResource\Pages;

use App\Filament\Resources\ReportRunResource;
use Filament\Resources\Pages\ListRecords;

class ListReportRuns extends ListRecords
{
    protected static string $resource = ReportRunResource::class;
}
