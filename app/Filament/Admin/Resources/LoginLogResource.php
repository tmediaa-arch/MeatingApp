<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Audit\Models\LoginLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'ممیزی و امنیت';
    protected static ?int $navigationSort = 82;

    public static function getModelLabel(): string
    {
        return 'لاگ ورود';
    }

    public static function getPluralModelLabel(): string
    {
        return 'لاگ‌های ورود';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('username_attempted')
                    ->label('نام کاربری')
                    ->searchable(),

                Tables\Columns\TextColumn::make('result')
                    ->label('نتیجه')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'failed_credentials', 'failed_user_not_found', 'failed_user_inactive' => 'danger',
                        'locked' => 'warning',
                        'mfa_failed' => 'danger',
                        'mfa_passed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'success' => 'موفق',
                        'failed_credentials' => 'رمز اشتباه',
                        'failed_user_not_found' => 'کاربر ناموجود',
                        'failed_user_inactive' => 'کاربر غیرفعال',
                        'locked' => 'قفل',
                        'mfa_failed' => 'MFA ناموفق',
                        'mfa_passed' => 'MFA موفق',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('auth_method')
                    ->label('روش')
                    ->badge(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('logged_out_at')
                    ->label('زمان خروج')
                    ->dateTime('Y/m/d H:i')
                    ->toggleable(),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('result')
                    ->label('نتیجه')
                    ->multiple()
                    ->options([
                        'success' => 'موفق',
                        'failed_credentials' => 'رمز اشتباه',
                        'failed_user_not_found' => 'کاربر ناموجود',
                        'failed_user_inactive' => 'غیرفعال',
                        'locked' => 'قفل',
                        'mfa_failed' => 'MFA ناموفق',
                    ]),

                Tables\Filters\Filter::make('performed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('از'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('تا'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('performed_at', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('performed_at', '<=', $d));
                    }),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\LoginLogResource\Pages\ListLoginLogs::route('/'),
        ];
    }
}
