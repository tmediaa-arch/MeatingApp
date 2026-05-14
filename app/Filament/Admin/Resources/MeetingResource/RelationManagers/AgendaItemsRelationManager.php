<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AgendaItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'agendaItemsRelation';
    protected static ?string $title = 'دستور جلسه';
    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('presenter_employee_id')
                    ->label('ارائه‌دهنده')
                    ->relationship('presenter', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                    ->searchable()
                    ->preload(),
                TextInput::make('estimated_duration_minutes')
                    ->label('مدت تخمینی (دقیقه)')
                    ->numeric()
                    ->default(15)
                    ->minValue(1)
                    ->maxValue(480),
                Select::make('item_type')
                    ->label('نوع')
                    ->options([
                        'discussion' => 'بحث',
                        'decision' => 'تصمیم‌گیری',
                        'information' => 'اطلاع‌رسانی',
                        'presentation' => 'ارائه',
                        'voting' => 'رأی‌گیری',
                        'review' => 'بازبینی',
                        'other' => 'سایر',
                    ])
                    ->default('discussion')
                    ->required(),
                Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'in_progress' => 'در حال بحث',
                        'discussed' => 'بحث شد',
                        'deferred' => 'موکول شد',
                        'cancelled' => 'لغو شد',
                    ])
                    ->default('pending'),
                TextInput::make('order_index')
                    ->label('ترتیب')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_index')
                    ->label('#')
                    ->width(50),
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('presenter.full_name')
                    ->label('ارائه‌دهنده')
                    ->default('—'),
                Tables\Columns\TextColumn::make('estimated_duration_minutes')
                    ->label('مدت')
                    ->formatStateUsing(fn ($state) => $state . ' دقیقه'),
                Tables\Columns\TextColumn::make('item_type')
                    ->label('نوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
            ])
            ->defaultSort('order_index')
            ->reorderable('order_index')
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن دستور'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
