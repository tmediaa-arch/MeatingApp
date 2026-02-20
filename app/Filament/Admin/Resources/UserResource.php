<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Identity\Actions\AssignRoleAction;
use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\SuspendUserAction;
use App\Domains\Identity\Actions\UnlockUserAction;
use App\Domains\Identity\DTOs\CreateUserData;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Filament\Admin\Resources\UserResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Class UserResource
 *
 * Filament Resource برای مدیریت کاربران.
 *
 * نکات مهم:
 * 1. عملیات create توسط CreateUserAction انجام می‌شود — نه مستقیماً
 *    در این Resource. ما در `mutateFormDataBeforeCreate` به Action تحویل می‌دهیم.
 * 2. عملیات suspend, unlock, assign-role هم از طریق Action انجام می‌شوند.
 * 3. این Resource فقط یک «نما» است — منطق کسب‌وکار اینجا نوشته نمی‌شود.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'کاربر';
    }

    public static function getPluralModelLabel(): string
    {
        return 'کاربران';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات هویتی')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('username')
                        ->label('نام کاربری')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?User $record) => $record?->is_system),

                    Forms\Components\TextInput::make('national_code')
                        ->label('کد ملی')
                        ->length(10)
                        ->unique(ignoreRecord: true)
                        ->nullable(),

                    Forms\Components\TextInput::make('first_name')
                        ->label('نام')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('last_name')
                        ->label('نام خانوادگی')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('display_name')
                        ->label('نام نمایشی')
                        ->maxLength(200),

                    Forms\Components\TextInput::make('email')
                        ->label('ایمیل')
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->maxLength(200),

                    Forms\Components\TextInput::make('mobile')
                        ->label('موبایل')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('phone')
                        ->label('تلفن ثابت')
                        ->tel()
                        ->maxLength(20),
                ]),

            Forms\Components\Section::make('احراز هویت')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->label('رمز عبور')
                        ->password()
                        ->revealable()
                        ->minLength(10)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->visible(fn (?User $record) => $record === null || !$record->ldap_guid),

                    Forms\Components\Toggle::make('mfa_enabled')
                        ->label('احراز دو مرحله‌ای')
                        ->default(false),
                ]),

            Forms\Components\Section::make('وضعیت و دسترسی')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(fn () => collect(UserStatus::cases())
                            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                            ->toArray())
                        ->default(UserStatus::Active->value)
                        ->required(),

                    Forms\Components\Toggle::make('is_external')
                        ->label('کاربر خارجی')
                        ->helperText('مهمان بیرونی (کارمند داخلی نیست)'),

                    Forms\Components\Select::make('roles')
                        ->label('نقش‌ها')
                        ->relationship('roles', 'display_name')
                        ->preload()
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('یکپارچه‌سازی')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('ldap_guid')->label('LDAP GUID'),
                    Forms\Components\TextInput::make('ldap_domain')->label('LDAP Domain'),
                    Forms\Components\TextInput::make('sso_subject')->label('SSO Subject'),
                    Forms\Components\TextInput::make('hrs_employee_code')->label('کد پرسنلی HRS'),
                ]),

            Forms\Components\Section::make('تنظیمات کاربری')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('preferred_locale')
                        ->label('زبان')
                        ->options(['fa' => 'فارسی', 'en' => 'English', 'ar' => 'العربية'])
                        ->default('fa'),
                    Forms\Components\Select::make('preferred_calendar')
                        ->label('تقویم')
                        ->options(['jalali' => 'شمسی', 'gregorian' => 'میلادی'])
                        ->default('jalali'),
                    Forms\Components\TextInput::make('timezone')
                        ->label('منطقه زمانی')
                        ->default('Asia/Tehran'),
                ]),
        ]);
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
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => $state->color())
                    ->icon(fn (UserStatus $state) => $state->icon()),

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
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(fn () => collect(UserStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray()),

                Tables\Filters\TernaryFilter::make('is_external')
                    ->label('خارجی'),

                Tables\Filters\TernaryFilter::make('mfa_enabled')
                    ->label('MFA فعال'),

                Tables\Filters\SelectFilter::make('roles')
                    ->label('نقش')
                    ->relationship('roles', 'display_name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('suspend')
                    ->label('تعلیق')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (User $record) => !$record->is_system && $record->status === UserStatus::Active)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('دلیل تعلیق')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data, SuspendUserAction $action) {
                        $action->execute($record, $data['reason']);
                    }),

                Tables\Actions\Action::make('unlock')
                    ->label('بازکردن')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (User $record) => $record->isLocked())
                    ->requiresConfirmation()
                    ->action(function (User $record, UnlockUserAction $action) {
                        $action->execute($record, 'بازکردن دستی توسط ادمین');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
