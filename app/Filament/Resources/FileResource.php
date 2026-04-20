<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Files\Models\File;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Resources\FileResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'فایل‌ها';
    protected static ?string $modelLabel = 'فایل';
    protected static ?string $pluralModelLabel = 'فایل‌ها';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('مشخصات فایل')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('storage_path')
                        ->label('فایل')
                        ->disk('local')
                        ->directory('files')
                        ->visibility('private')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('confidentiality_level')
                        ->label('سطح محرمانگی')
                        ->options(ConfidentialityLevel::class)
                        ->required()
                        ->default('internal'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('تاریخ انقضا'),
                    Forms\Components\TagsInput::make('tags')
                        ->label('تگ‌ها')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('icon')
                    ->label('')
                    ->state(fn ($r) => match (true) {
                        str_contains($r->mime_type ?? '', 'pdf') => 'heroicon-o-document',
                        str_contains($r->mime_type ?? '', 'image') => 'heroicon-o-photo',
                        str_contains($r->mime_type ?? '', 'video') => 'heroicon-o-film',
                        default => 'heroicon-o-document-text',
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
                Tables\Filters\SelectFilter::make('confidentiality_level')
                    ->label('محرمانگی')
                    ->options(ConfidentialityLevel::class),
                Tables\Filters\SelectFilter::make('virus_scan_status')
                    ->label('اسکن')
                    ->options([
                        'pending' => 'در انتظار',
                        'clean' => 'سالم',
                        'infected' => 'آلوده',
                        'failed' => 'ناموفق',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('دانلود')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (File $r) => auth()->user()->can('download', $r))
                    ->action(function (File $r) {
                        return response()->download(
                            storage_path('app/' . $r->storage_path),
                            $r->file_name,
                        );
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('uploaded_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        // فقط فایل‌هایی که کاربر قابلیت دیدنشان را دارد
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
