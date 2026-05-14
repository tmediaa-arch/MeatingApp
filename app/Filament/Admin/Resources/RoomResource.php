<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Rooms\Enums\RoomStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Resources\RoomResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
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
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static ?string $navigationLabel = 'سالن‌ها';
    protected static ?string $modelLabel = 'سالن';
    protected static ?string $pluralModelLabel = 'سالن‌ها';
    protected static ?string $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات کلی')
                    ->columns(2)
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
                    ]),

                Section::make('ظرفیت و چیدمان')
                    ->columns(3)
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
                    ]),

                Section::make('مکان')
                    ->columns(3)
                    ->collapsed()
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
                    ]),

                Section::make('تجهیزات و امکانات')
                    ->columns(4)
                    ->schema([
                        Toggle::make('has_projector')->label('پروژکتور'),
                        Toggle::make('has_video_conference')->label('ویدئوکنفرانس'),
                        Toggle::make('has_whiteboard')->label('وایت‌برد'),
                        Toggle::make('has_audio_system')->label('سیستم صوتی'),
                        Toggle::make('has_recording')->label('ضبط صوت/تصویر'),
                        Toggle::make('has_wifi')->label('Wi-Fi')->default(true),
                        Toggle::make('has_accessibility')->label('دسترسی معلولین'),
                    ]),

                Section::make('سیاست رزرو')
                    ->columns(3)
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
                    ]),

                Section::make('ساعات کاری')
                    ->description('برای هر روز هفته، start و end را به فرمت HH:mm وارد کنید (مثلاً ۰۸:۰۰ تا ۱۷:۰۰)')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('working_hours')
                            ->label('ساعات کاری')
                            ->keyLabel('روز هفته')
                            ->valueLabel('ساعت‌ها')
                            ->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت')
                    ->schema([
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(RoomStatus::class)
                            ->default(RoomStatus::Active)
                            ->required(),
                        Select::make('confidentiality_level')
                            ->label('سطح محرمانگی')
                            ->options(ConfidentialityLevel::class)
                            ->default(ConfidentialityLevel::Internal)
                            ->required(),
                    ]),
            ],
        ));
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
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(RoomStatus::class),
                SelectFilter::make('organization_id')
                    ->label('سازمان')
                    ->relationship('organization', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
