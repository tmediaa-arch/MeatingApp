<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Organization\Models\OrgUnit;
use App\Filament\Admin\Resources\OrgUnitResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrgUnitResource extends Resource
{
    protected static ?string $model = OrgUnit::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'ساختار سازمانی';
    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return 'واحد سازمانی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'واحدهای سازمانی';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات اصلی')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('سازمان')
                        ->relationship('organization', 'name')
                        ->required()
                        ->default(fn () => \App\Domains\Organization\Models\Organization::first()?->id),

                    Forms\Components\Select::make('parent_id')
                        ->label('واحد والد')
                        ->relationship(
                            'parent',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->where('is_active', true),
                        )
                        ->searchable()
                        ->preload()
                        ->placeholder('— واحد ریشه —')
                        ->helperText('در صورت خالی گذاشتن، این واحد ریشه خواهد بود'),

                    Forms\Components\TextInput::make('code')
                        ->label('کد')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options(OrgUnitType::options())
                        ->required(),

                    Forms\Components\TextInput::make('name')
                        ->label('نام')
                        ->required()
                        ->maxLength(200),

                    Forms\Components\TextInput::make('short_name')
                        ->label('نام مختصر')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('english_name')
                        ->label('نام انگلیسی')
                        ->maxLength(200),

                    Forms\Components\Select::make('manager_employee_id')
                        ->label('مدیر واحد')
                        ->relationship('manager', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                        ->searchable()
                        ->preload(),
                ]),

            Forms\Components\Section::make('اطلاعات تماس و مکان')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('phone')->label('تلفن'),
                    Forms\Components\TextInput::make('email')->label('ایمیل')->email(),
                    Forms\Components\TextInput::make('location_building')->label('ساختمان'),
                    Forms\Components\TextInput::make('location_floor')->label('طبقه'),
                    Forms\Components\Textarea::make('address')->label('آدرس')->columnSpanFull(),
                ]),

            Forms\Components\Section::make('وضعیت و نمایش')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
                    Forms\Components\TextInput::make('display_order')
                        ->label('ترتیب نمایش')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level')
                    ->label('سطح')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (OrgUnit $record) => str_repeat('— ', max(0, $record->level - 1)) . $record->name),

                Tables\Columns\TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (OrgUnitType $state) => $state->label()),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('والد')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manager.first_name')
                    ->label('مدیر')
                    ->getStateUsing(fn (OrgUnit $r) => $r->manager?->full_name)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('کارمندان')
                    ->counts('employees')
                    ->badge(),
            ])
            ->defaultSort('path', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع')
                    ->options(OrgUnitType::options()),

                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('والد')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrgUnits::route('/'),
            'create' => Pages\CreateOrgUnit::route('/create'),
            'view' => Pages\ViewOrgUnit::route('/{record}'),
            'edit' => Pages\EditOrgUnit::route('/{record}/edit'),
        ];
    }
}
