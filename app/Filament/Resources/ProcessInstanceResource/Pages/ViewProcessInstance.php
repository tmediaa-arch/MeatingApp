<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessInstanceResource\Pages;

use App\Filament\Resources\ProcessInstanceResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProcessInstance extends ViewRecord
{
    protected static string $resource = ProcessInstanceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات Instance')
                ->columns(3)
                ->schema([
                    TextEntry::make('instance_uuid')->label('UUID')->copyable(),
                    TextEntry::make('process_key')->label('کلید فرایند')->badge(),
                    TextEntry::make('process_version')->label('نسخه')->badge(),
                    TextEntry::make('business_key')->label('کلید کسب‌وکار'),
                    TextEntry::make('status')->label('وضعیت')->badge(),
                    TextEntry::make('priority')->label('اولویت')->badge(),
                    TextEntry::make('starter.name')->label('شروع‌کننده'),
                    TextEntry::make('started_at')->label('شروع')->dateTime('Y/m/d H:i'),
                    TextEntry::make('completed_at')->label('پایان')->dateTime('Y/m/d H:i')->placeholder('—'),
                    TextEntry::make('sla_due_at')->label('SLA')->dateTime('Y/m/d H:i')->placeholder('—'),
                    TextEntry::make('end_reason')->label('دلیل پایان')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('نمای گرافیکی BPMN (با وضعیت فعلی)')->schema([
                ViewEntry::make('bpmn_runtime')
                    ->view('filament.workflow.bpmn-runtime-viewer')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
