<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'پیوست‌ها';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('file_id')
                ->label('فایل')
                ->relationship('attachments', 'original_name')
                ->searchable()
                ->required(),

            Forms\Components\Select::make('purpose')
                ->label('کاربرد')
                ->options([
                    'quote' => 'پیش‌فاکتور',
                    'invoice' => 'فاکتور',
                    'evidence' => 'مدرک',
                    'reference' => 'مرجع',
                    'other' => 'سایر',
                ]),

            Forms\Components\Textarea::make('description')
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
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn ($action) => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('purpose')
                            ->label('کاربرد')
                            ->options([
                                'quote' => 'پیش‌فاکتور',
                                'invoice' => 'فاکتور',
                                'evidence' => 'مدرک',
                                'reference' => 'مرجع',
                                'other' => 'سایر',
                            ]),
                        Forms\Components\Textarea::make('description')->label('توضیح')->rows(2),
                        Forms\Components\Hidden::make('uploaded_by_user_id')->default(auth()->id()),
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
