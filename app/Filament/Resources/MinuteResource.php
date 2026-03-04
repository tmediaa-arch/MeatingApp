<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Resources\MinuteResource\Pages;
use App\Filament\Resources\MinuteResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MinuteResource extends Resource
{
    protected static ?string $model = Minute::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'صورت‌جلسات';
    protected static ?string $modelLabel = 'صورتجلسه';
    protected static ?string $pluralModelLabel = 'صورت‌جلسات';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات کلی')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('minute_number')
                        ->label('شماره صورتجلسه')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('meeting_id')
                        ->label('جلسه')
                        ->relationship('meeting', 'subject')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('summary')
                        ->label('خلاصه')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('محتوای صورتجلسه')
                ->schema([
                    Forms\Components\RichEditor::make('content_html')
                        ->label('متن صورتجلسه')
                        ->fileAttachmentsDirectory('minutes/attachments')
                        ->columnSpanFull(),

                    Forms\Components\TagsInput::make('key_decisions')
                        ->label('تصمیمات کلیدی')
                        ->placeholder('برای افزودن Enter بزنید')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('وضعیت و امضاها')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(MinuteStatus::options())
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('confidentiality_level')
                        ->label('سطح محرمانگی')
                        ->options(ConfidentialityLevel::class)
                        ->required(),

                    Forms\Components\Placeholder::make('secretary_signed_at')
                        ->label('امضای دبیر')
                        ->content(fn ($record) => $record?->secretary_signed_at
                            ? $record->secretary_signed_at->format('Y/m/d H:i')
                            : '— امضا نشده'),

                    Forms\Components\Placeholder::make('chairperson_signed_at')
                        ->label('امضای رئیس')
                        ->content(fn ($record) => $record?->chairperson_signed_at
                            ? $record->chairperson_signed_at->format('Y/m/d H:i')
                            : '— امضا نشده'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('minute_number')
                    ->label('شماره')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('meeting.meeting_number')
                    ->label('شماره جلسه')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (MinuteStatus $state) => $state->color())
                    ->formatStateUsing(fn (MinuteStatus $state) => $state->label()),

                Tables\Columns\IconColumn::make('secretary_signed_at')
                    ->label('امضای دبیر')
                    ->boolean(),

                Tables\Columns\IconColumn::make('chairperson_signed_at')
                    ->label('امضای رئیس')
                    ->boolean(),

                Tables\Columns\TextColumn::make('current_version')
                    ->label('نسخه'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(MinuteStatus::options()),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('سازمان')
                    ->relationship('organization', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Minute $minute) => $minute->status->isEditable()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VersionsRelationManager::class,
            RelationManagers\SignaturesRelationManager::class,
            RelationManagers\ResolutionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMinutes::route('/'),
            'create' => Pages\CreateMinute::route('/create'),
            'view' => Pages\ViewMinute::route('/{record}'),
            'edit' => Pages\EditMinute::route('/{record}/edit'),
        ];
    }
}
