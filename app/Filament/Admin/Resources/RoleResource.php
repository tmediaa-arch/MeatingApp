<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoleResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|\UnitEnum|null $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 15;
    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return 'نقش';
    }

    public static function getPluralModelLabel(): string
    {
        return 'نقش‌ها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات نقش')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('کد نقش')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Role $record) => $record?->is_system)
                            ->helperText('با حروف کوچک انگلیسی و خط تیره (مثل: meeting-secretary)'),

                        TextInput::make('display_name')
                            ->label('نام نمایشی')
                            ->required(),

                        Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull(),
                    ]),

                Section::make('دسترسی‌ها')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('دسترسی‌های نقش')
                            ->relationship('permissions', 'display_name')
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable()
                            ->disabled(fn (?Role $record) => $record?->name === 'super-admin'),
                    ]),
            ],
            sidebar: [
                Section::make('تنظیمات')
                    ->schema([
                        TextInput::make('priority')
                            ->label('اولویت')
                            ->numeric()
                            ->default(100)
                            ->helperText('هرچه بیشتر، اولویت بالاتر'),

                        Toggle::make('is_assignable')
                            ->label('قابل انتساب به کاربران')
                            ->default(true),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('کد')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('نام نمایشی')
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('کاربران')
                    ->counts('users')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('دسترسی‌ها')
                    ->counts('permissions')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('is_system')
                    ->label('سیستمی')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_assignable')
                    ->label('قابل انتساب')
                    ->boolean(),
            ])
            ->defaultSort('priority', 'desc')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (Role $record) => !$record->is_system),
                ]),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return !$record->is_system;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
