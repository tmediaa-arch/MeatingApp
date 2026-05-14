<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessDefinitionResource\Pages;

use App\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProcessDefinition extends ViewRecord
{
    protected static string $resource = ProcessDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات فرایند')
                ->columns(2)
                ->schema([
                    TextEntry::make('process_key')->label('کلید'),
                    TextEntry::make('version')->label('نسخه')->badge(),
                    TextEntry::make('name')->label('نام'),
                    TextEntry::make('status')->label('وضعیت')->badge(),
                    TextEntry::make('category')->label('دسته')->badge(),
                    TextEntry::make('description')->label('شرح')->columnSpanFull(),
                ]),

            Section::make('نمایش BPMN')->schema([
                ViewEntry::make('bpmn_viewer')
                    ->view('filament.workflow.bpmn-viewer')
                    ->columnSpanFull(),
            ]),

            Section::make('XML خام')
                ->collapsed()
                ->schema([
                    TextEntry::make('bpmn_xml')
                        ->hiddenLabel()
                        ->copyable()
                        ->formatStateUsing(fn ($state) => "<pre style='direction:ltr;text-align:left;font-family:monospace;font-size:11px;max-height:400px;overflow:auto'>" . e($state) . "</pre>")
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
