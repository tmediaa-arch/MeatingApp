<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequestResource;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مشخصات درخواست')
                ->columns(2)
                ->schema([
                    TextEntry::make('request_number')->label('شماره'),
                    TextEntry::make('type')
                        ->label('نوع')
                        ->badge(),
                    TextEntry::make('status')
                        ->label('وضعیت')
                        ->badge(),
                    TextEntry::make('priority')->label('اولویت'),
                    TextEntry::make('title')->label('عنوان')->columnSpanFull(),
                    TextEntry::make('description')->label('شرح')->columnSpanFull(),
                ]),

            Section::make('داده‌های اختصاصی')
                ->collapsible()
                ->schema([
                    KeyValueEntry::make('type_specific_data')
                        ->label('داده‌های اختصاصی نوع')
                        ->columnSpanFull(),
                ]),

            Section::make('زمان‌بندی')
                ->columns(3)
                ->collapsible()
                ->schema([
                    TextEntry::make('required_at')->label('زمان نیاز')->dateTime('Y/m/d H:i'),
                    TextEntry::make('estimated_duration_minutes')->label('مدت تخمینی (دقیقه)'),
                    TextEntry::make('submitted_at')->label('زمان ارسال')->dateTime('Y/m/d H:i'),
                    TextEntry::make('reviewed_at')->label('زمان بررسی')->dateTime('Y/m/d H:i'),
                    TextEntry::make('completed_at')->label('زمان تکمیل')->dateTime('Y/m/d H:i'),
                ]),

            Section::make('اشخاص و واحدها')
                ->columns(2)
                ->collapsible()
                ->schema([
                    TextEntry::make('requester.name')->label('درخواست‌کننده'),
                    TextEntry::make('reviewer.name')->label('بررسی‌کننده')->placeholder('—'),
                    TextEntry::make('assignedEmployee.full_name')->label('مجری')->placeholder('—'),
                    TextEntry::make('providerUnit.name')->label('واحد ارائه‌دهنده')->placeholder('—'),
                    TextEntry::make('meeting.subject')->label('جلسه مرتبط')->placeholder('—'),
                ]),

            Section::make('هزینه')
                ->columns(2)
                ->collapsible()
                ->schema([
                    TextEntry::make('estimated_cost')->label('هزینه تخمینی')->money('IRR', divideBy: 1),
                    TextEntry::make('actual_cost')->label('هزینه واقعی')->money('IRR', divideBy: 1)->placeholder('—'),
                ]),

            Section::make('یادداشت بررسی')
                ->visible(fn ($record) => filled($record->review_comment))
                ->collapsible()
                ->schema([
                    TextEntry::make('review_comment')->label('یادداشت')->columnSpanFull(),
                ]),
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
