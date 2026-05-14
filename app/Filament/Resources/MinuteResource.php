<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\MinuteResource\Pages;
use App\Filament\Resources\MinuteResource\RelationManagers;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MinuteResource extends Resource
{
    protected static ?string $model = Minute::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'صورت‌جلسات';
    protected static ?string $modelLabel = 'صورتجلسه';
    protected static ?string $pluralModelLabel = 'صورت‌جلسات';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات کلی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('minute_number')
                            ->label('شماره صورتجلسه')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('meeting_id')
                            ->label('جلسه')
                            ->relationship('meeting', 'subject')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Textarea::make('summary')
                            ->label('خلاصه')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('محتوای صورتجلسه')
                    ->schema([
                        RichEditor::make('content_html')
                            ->label('متن صورتجلسه')
                            ->fileAttachmentsDirectory('minutes/attachments')
                            ->columnSpanFull(),

                        TagsInput::make('key_decisions')
                            ->label('تصمیمات کلیدی')
                            ->placeholder('برای افزودن Enter بزنید')
                            ->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت و محرمانگی')
                    ->schema([
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(MinuteStatus::class)
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('confidentiality_level')
                            ->label('سطح محرمانگی')
                            ->options(ConfidentialityLevel::class)
                            ->required(),
                    ]),

                Section::make('امضاها')
                    ->schema([
                        TextEntry::make('secretary_signed_at')
                            ->label('امضای دبیر')
                            ->state(fn ($record) => $record?->secretary_signed_at
                                ? $record->secretary_signed_at->format('Y/m/d H:i')
                                : '— امضا نشده'),

                        TextEntry::make('chairperson_signed_at')
                            ->label('امضای رئیس')
                            ->state(fn ($record) => $record?->chairperson_signed_at
                                ? $record->chairperson_signed_at->format('Y/m/d H:i')
                                : '— امضا نشده'),
                    ]),
            ],
        ));
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
                    ->badge(),

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
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(MinuteStatus::class),
                SelectFilter::make('organization_id')
                    ->label('سازمان')
                    ->relationship('organization', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (Minute $minute) => $minute->status->isEditable()),
                ]),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
