<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Identity\Actions\RevokeDelegationAction;
use App\Domains\Identity\Models\UserDelegation;
use App\Filament\Admin\Resources\DelegationResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DelegationResource extends Resource
{
    protected static ?string $model = UserDelegation::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-right';
    protected static ?string $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 18;

    public static function getModelLabel(): string
    {
        return 'تفویض اختیار';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تفویض‌های اختیار';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('طرفین تفویض')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('delegator_user_id')
                        ->label('تفویض‌کننده')
                        ->relationship('delegator', 'username')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r->resolved_display_name . ' (' . $r->username . ')')
                        ->searchable(['username', 'first_name', 'last_name'])
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('delegate_user_id')
                        ->label('کاربر نماینده')
                        ->relationship('delegate', 'username')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r->resolved_display_name . ' (' . $r->username . ')')
                        ->searchable(['username', 'first_name', 'last_name'])
                        ->preload()
                        ->required()
                        ->different('delegator_user_id'),
                ]),

            Forms\Components\Section::make('محدوده و زمان')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('scope')
                        ->label('محدوده')
                        ->options([
                            'all' => 'همه (کامل)',
                            'meetings' => 'مدیریت جلسات',
                            'signatures' => 'امضا صورتجلسه/مصوبه',
                            'approvals' => 'تأییدات',
                            'tasks' => 'وظایف و مصوبات',
                            'inbox' => 'کارتابل',
                        ])
                        ->default('meetings')
                        ->required()
                        ->reactive(),

                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options([
                            'pending' => 'در انتظار',
                            'active' => 'فعال',
                            'expired' => 'منقضی',
                            'revoked' => 'لغو شده',
                            'completed' => 'تکمیل شده',
                        ])
                        ->default('pending'),

                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('شروع')
                        ->required()
                        ->jalali(),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('پایان')
                        ->required()
                        ->after('starts_at')
                        ->jalali(),
                ]),

            Forms\Components\Section::make('توضیحات')
                ->schema([
                    Forms\Components\Textarea::make('reason')
                        ->label('دلیل تفویض')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('decree_number')
                        ->label('شماره ابلاغ')
                        ->maxLength(100),

                    Forms\Components\Toggle::make('notify_on_action')
                        ->label('اطلاع‌رسانی هنگام اقدام')
                        ->default(true)
                        ->helperText('در صورت فعال بودن، با هر اقدام نماینده، تفویض‌کننده مطلع می‌شود.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('delegator.username')
                    ->label('تفویض‌کننده')
                    ->searchable(),

                Tables\Columns\TextColumn::make('delegate.username')
                    ->label('نماینده')
                    ->searchable(),

                Tables\Columns\TextColumn::make('scope')
                    ->label('محدوده')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'all' => 'همه',
                        'meetings' => 'جلسات',
                        'signatures' => 'امضا',
                        'approvals' => 'تأییدات',
                        'tasks' => 'وظایف',
                        'inbox' => 'کارتابل',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('شروع')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('پایان')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'expired' => 'gray',
                        'revoked' => 'danger',
                        'completed' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('actions_count')
                    ->label('اقدامات')
                    ->numeric()
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'active' => 'فعال',
                        'expired' => 'منقضی',
                        'revoked' => 'لغو شده',
                        'completed' => 'تکمیل شده',
                    ]),

                Tables\Filters\SelectFilter::make('scope')
                    ->label('محدوده')
                    ->options([
                        'all' => 'همه',
                        'meetings' => 'جلسات',
                        'signatures' => 'امضا',
                        'approvals' => 'تأییدات',
                        'tasks' => 'وظایف',
                        'inbox' => 'کارتابل',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (UserDelegation $r) => in_array($r->status, ['pending'], true)),

                Tables\Actions\Action::make('revoke')
                    ->label('لغو تفویض')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (UserDelegation $r) => in_array($r->status, ['pending', 'active'], true))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('دلیل لغو')->required(),
                    ])
                    ->action(function (UserDelegation $record, array $data) {
                        app(RevokeDelegationAction::class)->execute($record, $data['reason']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDelegations::route('/'),
            'create' => Pages\CreateDelegation::route('/create'),
            'view' => Pages\ViewDelegation::route('/{record}'),
            'edit' => Pages\EditDelegation::route('/{record}/edit'),
        ];
    }
}
