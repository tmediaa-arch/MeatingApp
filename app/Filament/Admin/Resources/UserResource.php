<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Identity\Actions\SuspendUserAction;
use App\Domains\Identity\Actions\UnlockUserAction;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static ?string $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'username';

    public static function getModelLabel(): string
    {
        return 'کاربر';
    }

    public static function getPluralModelLabel(): string
    {
        return 'کاربران';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات هویتی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('username')
                            ->label('نام کاربری')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?User $record) => $record?->is_system),

                        TextInput::make('national_code')
                            ->label('کد ملی')
                            ->length(10)
                            ->unique(ignoreRecord: true)
                            ->nullable(),

                        TextInput::make('first_name')
                            ->label('نام')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('last_name')
                            ->label('نام خانوادگی')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('display_name')
                            ->label('نام نمایشی')
                            ->maxLength(200),

                        TextInput::make('email')
                            ->label('ایمیل')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(200),

                        TextInput::make('mobile')
                            ->label('موبایل')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('phone')
                            ->label('تلفن ثابت')
                            ->tel()
                            ->maxLength(20),
                    ]),

                Section::make('احراز هویت')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->label('رمز عبور')
                            ->password()
                            ->revealable()
                            ->minLength(10)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->visible(fn (?User $record) => $record === null || !$record->ldap_guid),

                        Toggle::make('mfa_enabled')
                            ->label('احراز دو مرحله‌ای')
                            ->default(false),
                    ]),

                Section::make('یکپارچه‌سازی')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('ldap_guid')->label('LDAP GUID'),
                        TextInput::make('ldap_domain')->label('LDAP Domain'),
                        TextInput::make('sso_subject')->label('SSO Subject'),
                        TextInput::make('hrs_employee_code')->label('کد پرسنلی HRS'),
                    ]),

                Section::make('تنظیمات کاربری')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        Select::make('preferred_locale')
                            ->label('زبان')
                            ->options(['fa' => 'فارسی', 'en' => 'English', 'ar' => 'العربية'])
                            ->default('fa'),
                        Select::make('preferred_calendar')
                            ->label('تقویم')
                            ->options(['jalali' => 'شمسی', 'gregorian' => 'میلادی'])
                            ->default('jalali'),
                        TextInput::make('timezone')
                            ->label('منطقه زمانی')
                            ->default('Asia/Tehran'),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت و دسترسی')
                    ->schema([
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(UserStatus::class)
                            ->default(UserStatus::Active)
                            ->required(),

                        Toggle::make('is_external')
                            ->label('کاربر خارجی')
                            ->helperText('مهمان بیرونی (کارمند داخلی نیست)'),

                        Select::make('roles')
                            ->label('نقش‌ها')
                            ->relationship('roles', 'display_name')
                            ->preload()
                            ->multiple()
                            ->searchable(),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->label('نام کاربری')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('نام کامل')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),

                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('mobile')
                    ->label('موبایل')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),

                Tables\Columns\TextColumn::make('roles.display_name')
                    ->label('نقش‌ها')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_external')
                    ->label('خارجی')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('آخرین ورود')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(UserStatus::class),

                TernaryFilter::make('is_external')
                    ->label('خارجی'),

                TernaryFilter::make('mfa_enabled')
                    ->label('MFA فعال'),

                SelectFilter::make('roles')
                    ->label('نقش')
                    ->relationship('roles', 'display_name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('suspend')
                        ->label('تعلیق')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('warning')
                        ->visible(fn (User $record) => !$record->is_system && $record->status === UserStatus::Active)
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')
                                ->label('دلیل تعلیق')
                                ->required(),
                        ])
                        ->action(function (User $record, array $data, SuspendUserAction $action) {
                            $action->execute($record, $data['reason']);
                        }),

                    Action::make('unlock')
                        ->label('بازکردن')
                        ->icon(Heroicon::OutlinedKey)
                        ->color('success')
                        ->visible(fn (User $record) => $record->isLocked())
                        ->requiresConfirmation()
                        ->action(function (User $record, UnlockUserAction $action) {
                            $action->execute($record, 'بازکردن دستی توسط ادمین');
                        }),
                ]),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
