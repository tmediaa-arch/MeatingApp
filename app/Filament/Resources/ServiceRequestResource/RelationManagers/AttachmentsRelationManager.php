<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'پیوست‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('file_id')
                ->label('فایل')
                ->relationship('attachments', 'original_name')
                ->searchable()
                ->required(),

            Select::make('purpose')
                ->label('کاربرد')
                ->options([
                    'quote' => 'پیش‌فاکتور',
                    'invoice' => 'فاکتور',
                    'evidence' => 'مدرک',
                    'reference' => 'مرجع',
                    'other' => 'سایر',
                ]),

            Textarea::make('description')
                ->label('توضیح')
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                Tables\Columns\TextColumn::make('original_name')
                    ->label('نام فایل')
                    ->limit(40),

                Tables\Columns\TextColumn::make('pivot.purpose')
                    ->label('کاربرد')
                    ->badge(),

                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('اندازه')
                    ->formatStateUsing(fn ($state) => number_format($state / 1024, 1) . ' KB'),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('بارگذاری')
                    ->dateTime('Y/m/d H:i'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn ($action) => [
                        $action->getRecordSelect(),
                        Select::make('purpose')
                            ->label('کاربرد')
                            ->options([
                                'quote' => 'پیش‌فاکتور',
                                'invoice' => 'فاکتور',
                                'evidence' => 'مدرک',
                                'reference' => 'مرجع',
                                'other' => 'سایر',
                            ]),
                        Textarea::make('description')->label('توضیح')->rows(2),
                        Hidden::make('uploaded_by_user_id')->default(auth()->id()),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
