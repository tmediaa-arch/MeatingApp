<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Filament\Forms\Components\JalaliDatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'دسترسی‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('can_view')->label('مشاهده')->default(true),
            Toggle::make('can_download')->label('دانلود')->default(true),
            Toggle::make('can_share')->label('اشتراک‌گذاری'),
            Toggle::make('can_delete')->label('حذف'),
            JalaliDatePicker::make('expires_at')->label('انقضا')->dateTime(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.display_name')->label('کاربر')->placeholder('—'),
                Tables\Columns\TextColumn::make('role.display_name')->label('نقش')->placeholder('—'),
                Tables\Columns\IconColumn::make('can_view')->label('مشاهده')->boolean(),
                Tables\Columns\IconColumn::make('can_download')->label('دانلود')->boolean(),
                Tables\Columns\IconColumn::make('can_share')->label('اشتراک')->boolean(),
                Tables\Columns\IconColumn::make('can_delete')->label('حذف')->boolean(),
                Tables\Columns\TextColumn::make('expires_at')->label('انقضا')->dateTime('Y/m/d H:i')->placeholder('—'),
            ]);
    }
}
