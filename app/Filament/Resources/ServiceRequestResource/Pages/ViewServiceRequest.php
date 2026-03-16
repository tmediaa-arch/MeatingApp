<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Filament\Resources\ServiceRequestResource;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('مشخصات درخواست')->schema([
                Components\TextEntry::make('request_number')->label('شماره'),
                Components\TextEntry::make('type')
                    ->label('نوع')
                    ->badge()
                    ->color(fn (ServiceRequestType $t) => $t->color())
                    ->formatStateUsing(fn (ServiceRequestType $t) => $t->label()),
                Components\TextEntry::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (ServiceRequestStatus $s) => $s->color())
                    ->formatStateUsing(fn (ServiceRequestStatus $s) => $s->label()),
                Components\TextEntry::make('priority')->label('اولویت'),
                Components\TextEntry::make('title')->label('عنوان')->columnSpanFull(),
                Components\TextEntry::make('description')->label('شرح')->columnSpanFull(),
            ])->columns(2),

            Components\Section::make('داده‌های اختصاصی')->schema([
                Components\KeyValueEntry::make('type_specific_data')
                    ->label('داده‌های اختصاصی نوع')
                    ->columnSpanFull(),
            ])->collapsible(),

            Components\Section::make('زمان‌بندی')->schema([
                Components\TextEntry::make('required_at')->label('زمان نیاز')->dateTime('Y/m/d H:i'),
                Components\TextEntry::make('estimated_duration_minutes')->label('مدت تخمینی (دقیقه)'),
                Components\TextEntry::make('submitted_at')->label('زمان ارسال')->dateTime('Y/m/d H:i'),
                Components\TextEntry::make('reviewed_at')->label('زمان بررسی')->dateTime('Y/m/d H:i'),
                Components\TextEntry::make('completed_at')->label('زمان تکمیل')->dateTime('Y/m/d H:i'),
            ])->columns(3)->collapsible(),

            Components\Section::make('اشخاص و واحدها')->schema([
                Components\TextEntry::make('requester.name')->label('درخواست‌کننده'),
                Components\TextEntry::make('reviewer.name')->label('بررسی‌کننده')->placeholder('—'),
                Components\TextEntry::make('assignedEmployee.full_name')->label('مجری')->placeholder('—'),
                Components\TextEntry::make('providerUnit.name')->label('واحد ارائه‌دهنده')->placeholder('—'),
                Components\TextEntry::make('meeting.subject')->label('جلسه مرتبط')->placeholder('—'),
            ])->columns(2)->collapsible(),

            Components\Section::make('هزینه')->schema([
                Components\TextEntry::make('estimated_cost')->label('هزینه تخمینی')->money('IRR', divideBy: 1),
                Components\TextEntry::make('actual_cost')->label('هزینه واقعی')->money('IRR', divideBy: 1)->placeholder('—'),
            ])->columns(2)->collapsible(),

            Components\Section::make('یادداشت بررسی')->schema([
                Components\TextEntry::make('review_comment')->label('یادداشت')->columnSpanFull(),
            ])->visible(fn ($record) => filled($record->review_comment))->collapsible(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make()
                ->visible(fn () => $this->record->status === ServiceRequestStatus::Draft),
        ];
    }
}
