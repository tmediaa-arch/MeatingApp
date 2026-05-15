<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Files\Models\File;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\FileResource\Pages;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;
    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'فایل‌ها';
    protected static ?string $modelLabel = 'فایل';
    protected static ?string $pluralModelLabel = 'فایل‌ها';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('مشخصات فایل')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('storage_path')
                            ->label('فایل')
                            ->disk('local')
                            ->directory('files')
                            ->visibility('private')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('دسترسی و انقضا')
                    ->schema([
                        Select::make('confidentiality_level')
                            ->label('سطح محرمانگی')
                            ->options(ConfidentialityLevel::class)
                            ->required()
                            ->default('internal'),
                        DateTimePicker::make('expires_at')
                            ->label('تاریخ انقضا'),
                    ]),
                Section::make('برچسب‌ها')
                    ->collapsed()
                    ->schema([
                        TagsInput::make('tags')
                            ->label('تگ‌ها'),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('icon')
                    ->hiddenLabel()
                    ->state(fn ($r) => match (true) {
                        str_contains($r->mime_type ?? '', 'pdf') => Heroicon::OutlinedDocument,
                        str_contains($r->mime_type ?? '', 'image') => Heroicon::OutlinedPhoto,
                        str_contains($r->mime_type ?? '', 'video') => Heroicon::OutlinedFilm,
                        default => Heroicon::OutlinedDocumentText,
                    })
                    ->color('gray'),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('file_name')->label('نام فایل')->limit(30),
                Tables\Columns\TextColumn::make('mime_type')->label('نوع'),
                Tables\Columns\TextColumn::make('file_size_human')->label('حجم'),
                Tables\Columns\TextColumn::make('confidentiality_level')
                    ->label('محرمانگی')
                    ->badge(),
                Tables\Columns\TextColumn::make('virus_scan_status')
                    ->label('اسکن')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'clean' => 'success',
                        'infected' => 'danger',
                        'failed' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('uploadedBy.name')->label('بارگذار'),
                Tables\Columns\TextColumn::make('uploaded_at')->label('زمان')->dateTime('Y/m/d H:i'),
            ])
            ->filters([
                SelectFilter::make('confidentiality_level')
                    ->label('محرمانگی')
                    ->options(ConfidentialityLevel::class),
                SelectFilter::make('virus_scan_status')
                    ->label('اسکن')
                    ->options([
                        'pending' => 'در انتظار',
                        'clean' => 'سالم',
                        'infected' => 'آلوده',
                        'failed' => 'ناموفق',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('download')
                        ->label('دانلود')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn (File $r) => auth()->user()->can('download', $r))
                        ->action(function (File $r) {
                            return response()->download(
                                storage_path('app/' . $r->storage_path),
                                $r->file_name,
                            );
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('uploaded_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        return parent::getEloquentQuery()
            ->where(function ($q) use ($user) {
                $q->where('uploaded_by_user_id', $user->id)
                  ->orWhere('confidentiality_level', '<=', 'internal');
                if ($user->hasPermissionTo('file.view_all')) {
                    $q->orWhereRaw('1=1');
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            FileResource\RelationManagers\PermissionsRelationManager::class,
            FileResource\RelationManagers\AccessLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiles::route('/'),
            'create' => Pages\CreateFile::route('/create'),
            'edit' => Pages\EditFile::route('/{record}/edit'),
        ];
    }
}
