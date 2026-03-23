<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessDefinitionResource\Pages;

use App\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProcessDefinition extends ViewRecord
{
    protected static string $resource = ProcessDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('اطلاعات فرایند')->schema([
                TextEntry::make('process_key')->label('کلید'),
                TextEntry::make('version')->label('نسخه')->badge(),
                TextEntry::make('name')->label('نام'),
                TextEntry::make('status')->label('وضعیت')->badge(),
                TextEntry::make('category')->label('دسته')->badge(),
                TextEntry::make('description')->label('شرح')->columnSpanFull(),
            ])->columns(2),

            Section::make('نمایش BPMN')->schema([
                ViewEntry::make('bpmn_viewer')
                    ->view('filament.workflow.bpmn-viewer')
                    ->columnSpanFull(),
            ]),

            Section::make('XML خام')->schema([
                TextEntry::make('bpmn_xml')
                    ->label('')
                    ->copyable()
                    ->formatStateUsing(fn ($state) => "<pre style='direction:ltr;text-align:left;font-family:monospace;font-size:11px;max-height:400px;overflow:auto'>" . e($state) . "</pre>")
                    ->html()
                    ->columnSpanFull(),
            ])->collapsed(),
        ]);
    }
}
