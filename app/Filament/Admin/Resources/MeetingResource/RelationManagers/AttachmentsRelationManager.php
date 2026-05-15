<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(300),
                FileUpload::make('file_path')
                    ->label('فایل')
                    ->disk('local')
                    ->directory('meetings/attachments')
                    ->maxSize(50 * 1024)
                    ->required(),
                Select::make('attachment_type')
                    ->label('نوع پیوست')
                    ->options([
                        'agenda' => 'دستور جلسه',
                        'background' => 'پیش‌مطالعه',
                        'presentation' => 'ارائه',
                        'supporting' => 'مستندات پشتیبان',
                        'other' => 'سایر',
                    ])
                    ->default('background')
                    ->required(),
                Select::make('visibility')
                    ->label('سطح نمایش')
                    ->options([
                        'all_participants' => 'همه شرکت‌کنندگان',
                        'voting_members' => 'فقط اعضای رأی‌دهنده',
                        'chairperson_secretary' => 'فقط رئیس و دبیر',
                        'specific_roles' => 'نقش‌های مشخص',
                        'private' => 'خصوصی',
                    ])
                    ->default('all_participants')
                    ->required(),
                Toggle::make('is_circulated_before_meeting')
                    ->label('پیش از جلسه ارسال شود'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان'),
                Tables\Columns\TextColumn::make('attachment_type')
                    ->label('نوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('file_size_human')
                    ->label('حجم'),
                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('بارگذاری توسط')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_circulated_before_meeting')
                    ->label('قبل از جلسه')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن پیوست')
                    ->mutateDataUsing(function (array $data): array {
                        $data['uploaded_by_user_id'] = auth()->id();
                        $data['uploaded_at'] = now();
                        if (! empty($data['file_path']) && file_exists(storage_path('app/' . $data['file_path']))) {
                            $data['file_size_bytes'] = filesize(storage_path('app/' . $data['file_path']));
                            $data['file_name'] = basename($data['file_path']);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
