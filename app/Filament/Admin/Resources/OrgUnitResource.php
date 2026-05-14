<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Organization\Models\OrgUnit;
use App\Filament\Admin\Resources\OrgUnitResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrgUnitResource extends Resource
{
    protected static ?string $model = OrgUnit::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static ?string $navigationGroup = 'ساختار سازمانی';
    protected static ?int $navigationSort = 20;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'واحد سازمانی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'واحدهای سازمانی';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات اصلی')
                    ->columns(2)
                    ->schema([
                        Select::make('organization_id')
                            ->label('سازمان')
                            ->relationship('organization', 'name')
                            ->required()
                            ->default(fn () => \App\Domains\Organization\Models\Organization::first()?->id),

                        Select::make('parent_id')
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

                        TextInput::make('code')
                            ->label('کد')
                            ->required()
                            ->maxLength(50),

                        Select::make('type')
                            ->label('نوع')
                            ->options(OrgUnitType::class)
                            ->required(),

                        TextInput::make('name')
                            ->label('نام')
                            ->required()
                            ->maxLength(200),

                        TextInput::make('short_name')
                            ->label('نام مختصر')
                            ->maxLength(100),

                        TextInput::make('english_name')
                            ->label('نام انگلیسی')
                            ->maxLength(200),

                        Select::make('manager_employee_id')
                            ->label('مدیر واحد')
                            ->relationship('manager', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('اطلاعات تماس و مکان')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('phone')->label('تلفن'),
                        TextInput::make('email')->label('ایمیل')->email(),
                        TextInput::make('location_building')->label('ساختمان'),
                        TextInput::make('location_floor')->label('طبقه'),
                        Textarea::make('address')->label('آدرس')->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت و نمایش')
                    ->schema([
                        Toggle::make('is_active')->label('فعال')->default(true),
                        TextInput::make('display_order')
                            ->label('ترتیب نمایش')
                            ->numeric()
                            ->default(0),
                    ]),
            ],
        ));
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
                    ->badge(),

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
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(OrgUnitType::class),

                TernaryFilter::make('is_active')->label('فعال'),

                SelectFilter::make('parent_id')
                    ->label('والد')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
