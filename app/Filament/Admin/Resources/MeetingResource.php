<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Meetings\Enums\MeetingMode;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Enums\MeetingType;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Resources\MeetingResource\Pages;
use App\Filament\Admin\Resources\MeetingResource\RelationManagers;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeetingResource extends Resource
{
    protected static ?string $model = Meeting::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'جلسات';
    protected static ?string $modelLabel = 'جلسه';
    protected static ?string $pluralModelLabel = 'جلسات';
    protected static ?string $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // بخش اول: شناسایی و موضوع
            Section::make('اطلاعات کلی')
                ->description('شناسایی، موضوع و توضیحات جلسه')
                ->schema([
                    TextInput::make('meeting_number')
                        ->label('شماره جلسه')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('پس از ذخیره تولید می‌شود')
                        ->maxLength(50),
                    TextInput::make('subject')
                        ->label('موضوع جلسه')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500)
                        ->columnSpan(2),
                    Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(3),

            // بخش دوم: زمان‌بندی
            Section::make('زمان‌بندی')
                ->description('تاریخ، ساعت و نوع تکرار')
                ->schema([
                    DateTimePicker::make('scheduled_start_at')
                        ->label('شروع جلسه')
                        ->required()
                        ->seconds(false)
                        ->displayFormat('Y/m/d H:i')
                        ->minDate(now()),
                    DateTimePicker::make('scheduled_end_at')
                        ->label('پایان جلسه')
                        ->required()
                        ->seconds(false)
                        ->displayFormat('Y/m/d H:i')
                        ->after('scheduled_start_at'),
                    Select::make('timezone')
                        ->label('منطقه زمانی')
                        ->options([
                            'Asia/Tehran' => 'تهران',
                            'UTC' => 'UTC',
                        ])
                        ->default('Asia/Tehran')
                        ->required(),
                    Select::make('recurrence_pattern')
                        ->label('الگوی تکرار')
                        ->options([
                            'none' => 'یکبار',
                            'daily' => 'روزانه',
                            'weekly' => 'هفتگی',
                            'monthly' => 'ماهانه',
                            'custom' => 'سفارشی',
                        ])
                        ->default('none')
                        ->required(),
                ])
                ->columns(2),

            // بخش سوم: نوع و حالت
            Section::make('نوع و حالت برگزاری')
                ->schema([
                    Select::make('type')
                        ->label('نوع جلسه')
                        ->options(MeetingType::options())
                        ->default(MeetingType::Regular->value)
                        ->required(),
                    Select::make('mode')
                        ->label('حالت برگزاری')
                        ->options(MeetingMode::options())
                        ->default(MeetingMode::InPerson->value)
                        ->required()
                        ->live(),
                    Select::make('confidentiality_level')
                        ->label('سطح محرمانگی')
                        ->options(ConfidentialityLevel::options())
                        ->default(ConfidentialityLevel::Internal->value)
                        ->required(),
                ])
                ->columns(3),

            // بخش چهارم: مکان
            Section::make('مکان برگزاری')
                ->schema([
                    Select::make('room_id')
                        ->label('سالن')
                        ->relationship('room', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('انتخاب سالن')
                        ->visible(fn ($get) => $get('mode') !== MeetingMode::Online->value)
                        ->helperText('برای رزرو سالن، در دسترس بودن آن چک می‌شود.'),
                    TextInput::make('location_alt')
                        ->label('مکان جایگزین')
                        ->maxLength(500)
                        ->placeholder('در صورت نبود سالن داخلی'),
                    TextInput::make('video_meeting_url')
                        ->label('لینک ویدئوکنفرانس')
                        ->url()
                        ->maxLength(500)
                        ->visible(fn ($get) => $get('mode') !== MeetingMode::InPerson->value),
                ])
                ->columns(2),

            // بخش پنجم: افراد کلیدی
            Section::make('افراد کلیدی')
                ->schema([
                    Select::make('host_org_unit_id')
                        ->label('واحد میزبان')
                        ->relationship('hostOrgUnit', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('chairperson_employee_id')
                        ->label('رئیس جلسه')
                        ->relationship('chairperson', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                        ->searchable(['first_name', 'last_name', 'personnel_code'])
                        ->preload(),
                    Select::make('secretary_employee_id')
                        ->label('دبیر جلسه')
                        ->relationship('secretary', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                        ->searchable(['first_name', 'last_name', 'personnel_code'])
                        ->preload(),
                ])
                ->columns(3),

            // بخش ششم: دستور جلسه
            Section::make('دستور جلسه')
                ->description('موارد قابل بحث در جلسه')
                ->schema([
                    Repeater::make('agenda_items')
                        ->label('دستورات')
                        ->schema([
                            TextInput::make('title')
                                ->label('عنوان دستور')
                                ->required()
                                ->maxLength(500),
                            Textarea::make('description')
                                ->label('توضیحات')
                                ->rows(2),
                            TextInput::make('estimated_duration_minutes')
                                ->label('مدت تخمینی (دقیقه)')
                                ->numeric()
                                ->default(15)
                                ->minValue(1)
                                ->maxValue(480),
                            Select::make('item_type')
                                ->label('نوع')
                                ->options([
                                    'discussion' => 'بحث',
                                    'decision' => 'تصمیم‌گیری',
                                    'information' => 'اطلاع‌رسانی',
                                    'presentation' => 'ارائه',
                                    'voting' => 'رأی‌گیری',
                                    'review' => 'بازبینی',
                                    'other' => 'سایر',
                                ])
                                ->default('discussion'),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->reorderable()
                        ->collapsible()
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            // بخش هفتم: تنظیمات
            Section::make('تنظیمات')
                ->schema([
                    Toggle::make('allow_external_participants')
                        ->label('اجازه افراد خارج از سازمان')
                        ->default(false),
                    Toggle::make('require_confirmation')
                        ->label('پاسخ مدعو الزامی است')
                        ->default(true),
                    Toggle::make('record_attendance')
                        ->label('ثبت حضور و غیاب')
                        ->default(true),
                    Toggle::make('send_reminder')
                        ->label('ارسال یادآور')
                        ->default(true)
                        ->live(),
                    TextInput::make('reminder_minutes_before')
                        ->label('یادآور (دقیقه قبل)')
                        ->numeric()
                        ->default(60)
                        ->minValue(1)
                        ->maxValue(10080)
                        ->visible(fn ($get) => $get('send_reminder')),
                    Toggle::make('allow_late_join')
                        ->label('اجازه ورود دیرهنگام')
                        ->default(true),
                ])
                ->columns(3)
                ->collapsed(),

            // بخش هشتم: متادیتا
            Section::make('برچسب‌ها و متادیتا')
                ->schema([
                    TagsInput::make('tags')
                        ->label('برچسب‌ها')
                        ->columnSpanFull(),
                    KeyValue::make('metadata')
                        ->label('فیلدهای اضافه')
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('meeting_number')
                    ->label('شماره')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('موضوع')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('scheduled_start_at')
                    ->label('شروع')
                    ->formatStateUsing(fn ($state) => app(\App\Domains\Calendar\Services\JalaliCalendarService::class)
                        ->formatDateTime($state))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('مدت')
                    ->formatStateUsing(fn ($state) => $state . ' دقیقه')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (MeetingType $state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mode')
                    ->label('حالت')
                    ->icon(fn (MeetingMode $state) => $state->icon())
                    ->formatStateUsing(fn (MeetingMode $state) => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (MeetingStatus $state) => $state->color())
                    ->icon(fn (MeetingStatus $state) => $state->icon())
                    ->formatStateUsing(fn (MeetingStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('سالن')
                    ->toggleable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('chairperson.full_name')
                    ->label('رئیس جلسه')
                    ->toggleable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('شرکت‌کنندگان')
                    ->counts('participants')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('confidentiality_level')
                    ->label('محرمانگی')
                    ->badge()
                    ->formatStateUsing(fn (ConfidentialityLevel $state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('scheduled_start_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(MeetingStatus::options())
                    ->multiple(),
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(MeetingType::options()),
                SelectFilter::make('mode')
                    ->label('حالت')
                    ->options(MeetingMode::options()),
                Filter::make('upcoming')
                    ->label('آینده')
                    ->query(fn (Builder $query) => $query->upcoming()),
                Filter::make('past')
                    ->label('گذشته')
                    ->query(fn (Builder $query) => $query->past()),
                Filter::make('this_month')
                    ->label('این ماه')
                    ->query(function (Builder $query) {
                        $service = app(\App\Domains\Calendar\Services\JalaliCalendarService::class);
                        $now = now();
                        $jalali = \Morilog\Jalali\Jalalian::fromCarbon($now);
                        $bounds = $service->jalaliMonthBoundaries($jalali->getYear(), $jalali->getMonth());
                        return $query->between($bounds['start'], $bounds['end']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Meeting $record) => $record->status->isEditable()),
                Tables\Actions\Action::make('cancel')
                    ->label('لغو')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('لغو جلسه')
                    ->form([
                        Textarea::make('reason')
                            ->label('دلیل لغو')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Meeting $record, array $data) {
                        app(\App\Domains\Meetings\Actions\CancelMeetingAction::class)
                            ->execute($record, $data['reason']);

                        \Filament\Notifications\Notification::make()
                            ->title('جلسه لغو شد')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Meeting $record) => !$record->status->isTerminal()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('هنوز جلسه‌ای ایجاد نشده')
            ->emptyStateDescription('برای شروع، یک جلسه جدید ایجاد کنید.');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ParticipantsRelationManager::class,
            RelationManagers\AgendaItemsRelationManager::class,
            RelationManagers\StatusTransitionsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeetings::route('/'),
            'create' => Pages\CreateMeeting::route('/create'),
            'view' => Pages\ViewMeeting::route('/{record}'),
            'edit' => Pages\EditMeeting::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
