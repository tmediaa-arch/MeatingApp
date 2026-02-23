<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Rooms\Enums\RoomStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Resources\RoomResource\Pages;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'سالن‌ها';
    protected static ?string $modelLabel = 'سالن';
    protected static ?string $pluralModelLabel = 'سالن‌ها';
    protected static ?string $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('اطلاعات کلی')
                ->schema([
                    Select::make('organization_id')
                        ->label('سازمان')
                        ->relationship('organization', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('owner_org_unit_id')
                        ->label('واحد متولی')
                        ->relationship('ownerOrgUnit', 'name')
                        ->searchable()
                        ->preload(),
                    TextInput::make('code')
                        ->label('کد سالن')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('نام سالن')
                        ->required()
                        ->maxLength(200),
                    TextInput::make('english_name')
                        ->label('نام انگلیسی')
                        ->maxLength(200),
                    Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('ظرفیت و چیدمان')
                ->schema([
                    TextInput::make('capacity')
                        ->label('ظرفیت استاندارد')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(2000),
                    TextInput::make('max_capacity')
                        ->label('حداکثر ظرفیت')
                        ->numeric()
                        ->minValue(1),
                    Select::make('layout_type')
                        ->label('نوع چیدمان')
                        ->options([
                            'classroom' => 'کلاسی',
                            'u_shape' => 'U شکل',
                            'round_table' => 'میز گرد',
                            'theater' => 'تئاتری',
                            'boardroom' => 'اتاق هیئت‌مدیره',
                            'open' => 'باز',
                            'mixed' => 'ترکیبی',
                        ])
                        ->default('boardroom'),
                ])
                ->columns(3),

            Section::make('مکان')
                ->schema([
                    TextInput::make('building')
                        ->label('ساختمان')
                        ->maxLength(100),
                    TextInput::make('floor')
                        ->label('طبقه')
                        ->maxLength(50),
                    TextInput::make('room_number')
                        ->label('شماره اتاق')
                        ->maxLength(50),
                    Textarea::make('directions')
                        ->label('راهنمای دسترسی')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->collapsed(),

            Section::make('تجهیزات و امکانات')
                ->schema([
                    Toggle::make('has_projector')->label('پروژکتور'),
                    Toggle::make('has_video_conference')->label('ویدئوکنفرانس'),
                    Toggle::make('has_whiteboard')->label('وایت‌برد'),
                    Toggle::make('has_audio_system')->label('سیستم صوتی'),
                    Toggle::make('has_recording')->label('ضبط صوت/تصویر'),
                    Toggle::make('has_wifi')->label('Wi-Fi')->default(true),
                    Toggle::make('has_accessibility')->label('دسترسی معلولین'),
                ])
                ->columns(4),

            Section::make('سیاست رزرو')
                ->schema([
                    Select::make('reservation_policy')
                        ->label('سیاست رزرو')
                        ->options([
                            'free' => 'بدون تأیید',
                            'approval' => 'با تأیید مدیر سالن',
                            'restricted' => 'محدود به افراد مجاز',
                        ])
                        ->default('approval')
                        ->required(),
                    TextInput::make('min_booking_minutes')
                        ->label('حداقل مدت رزرو (دقیقه)')
                        ->numeric()
                        ->default(30),
                    TextInput::make('max_booking_minutes')
                        ->label('حداکثر مدت رزرو (دقیقه)')
                        ->numeric()
                        ->default(480),
                    TextInput::make('buffer_before_minutes')
                        ->label('فاصله قبل (دقیقه)')
                        ->numeric()
                        ->default(15),
                    TextInput::make('buffer_after_minutes')
                        ->label('فاصله بعد (دقیقه)')
                        ->numeric()
                        ->default(15),
                    TextInput::make('advance_booking_days')
                        ->label('حداکثر روز پیش‌رزرو')
                        ->numeric()
                        ->default(60),
                ])
                ->columns(3),

            Section::make('ساعات کاری')
                ->description('برای هر روز هفته، start و end را به فرمت HH:mm وارد کنید (مثلاً ۰۸:۰۰ تا ۱۷:۰۰)')
                ->schema([
                    KeyValue::make('working_hours')
                        ->label('ساعات کاری')
                        ->keyLabel('روز هفته')
                        ->valueLabel('ساعت‌ها')
                        ->columnSpanFull(),
                ])
                ->collapsed(),

            Section::make('وضعیت')
                ->schema([
                    Select::make('status')
                        ->label('وضعیت')
                        ->options(RoomStatus::options())
                        ->default(RoomStatus::Active->value)
                        ->required(),
                    Select::make('confidentiality_level')
                        ->label('سطح محرمانگی')
                        ->options(ConfidentialityLevel::options())
                        ->default(ConfidentialityLevel::Internal->value)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_location')
                    ->label('موقعیت')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('ظرفیت')
                    ->sortable(),
                Tables\Columns\TextColumn::make('layout_type')
                    ->label('چیدمان')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_projector')
                    ->label('پروژکتور')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_video_conference')
                    ->label('ویدئوکنفرانس')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reservation_policy')
                    ->label('سیاست رزرو')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (RoomStatus $state) => $state->color())
                    ->formatStateUsing(fn (RoomStatus $state) => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(RoomStatus::options()),
                SelectFilter::make('organization_id')
                    ->label('سازمان')
                    ->relationship('organization', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
