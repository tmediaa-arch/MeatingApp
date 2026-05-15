<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserTaskResource\Pages;

use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Filament\Resources\UserTaskResource;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUserTask extends ViewRecord
{
    protected static string $resource = UserTaskResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('UserTask')->schema([
                TextEntry::make('name')->label('عنوان'),
                TextEntry::make('description')->label('شرح'),
                TextEntry::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextEntry::make('priority')->label('اولویت')->badge(),
                TextEntry::make('assignee.name')->label('مجری')->placeholder('—'),
                TextEntry::make('due_at')->label('مهلت')->dateTime('Y/m/d H:i')->placeholder('—'),
            ])->columns(2),

            Section::make('Form Data')->schema([
                KeyValueEntry::make('form_data')->label('داده فرم'),
            ])->visible(fn ($record) => !empty($record->form_data)),

            Section::make('نتیجه')->schema([
                TextEntry::make('outcome')->label('نتیجه')->badge(),
                TextEntry::make('outcome_comment')->label('یادداشت')->columnSpanFull(),
                TextEntry::make('completed_at')->label('زمان تکمیل')->dateTime('Y/m/d H:i'),
                TextEntry::make('completedBy.name')->label('تکمیل توسط'),
            ])->columns(2)->visible(fn ($record) => $record->status === UserTaskStatus::Completed),

            Section::make('ارجاع')->schema([
                TextEntry::make('instance.process_key')->label('فرایند')->badge(),
                TextEntry::make('instance.business_key')->label('کلید کسب‌وکار'),
                TextEntry::make('element_id')->label('Element ID')->badge(),
            ])->columns(3),
        ]);
    }
}
