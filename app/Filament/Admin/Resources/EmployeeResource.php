<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Organization\Models\Employee;
use App\Filament\Admin\Resources\EmployeeResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Forms\Components\JalaliDatePicker;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
    protected static string|\UnitEnum|null $navigationGroup = 'ساختار سازمانی';
    protected static ?int $navigationSort = 30;
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getModelLabel(): string
    {
        return 'کارمند';
    }

    public static function getPluralModelLabel(): string
    {
        return 'کارمندان';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات هویتی')
                    ->columns(2)
                    ->schema([
                        Select::make('organization_id')
                            ->label('سازمان')
                            ->relationship('organization', 'name')
                            ->required(),

                        TextInput::make('employee_code')
                            ->label('کد پرسنلی')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),

                        TextInput::make('national_code')
                            ->label('کد ملی')
                            ->length(10)
                            ->unique(ignoreRecord: true),

                        TextInput::make('first_name')->label('نام')->required(),
                        TextInput::make('last_name')->label('نام خانوادگی')->required(),
                        TextInput::make('father_name')->label('نام پدر'),

                        Select::make('gender')
                            ->label('جنسیت')
                            ->options(['male' => 'مرد', 'female' => 'زن']),

                        JalaliDatePicker::make('birth_date')->label('تاریخ تولد'),
                    ]),

                Section::make('اطلاعات استخدامی')
                    ->columns(2)
                    ->schema([
                        Select::make('primary_position_id')
                            ->label('پست اصلی')
                            ->relationship('primaryPosition', 'title')
                            ->searchable()
                            ->preload(),

                        Select::make('current_org_unit_id')
                            ->label('واحد فعلی')
                            ->relationship('currentOrgUnit', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('reports_to_employee_id')
                            ->label('گزارش‌دهی به')
                            ->relationship('reportsTo', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                            ->searchable(),

                        JalaliDatePicker::make('hire_date')->label('تاریخ استخدام'),
                        JalaliDatePicker::make('termination_date')->label('تاریخ پایان همکاری'),
                    ]),

                Section::make('اطلاعات تماس')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('work_email')->label('ایمیل کاری')->email(),
                        TextInput::make('work_phone')->label('تلفن کاری'),
                        TextInput::make('mobile')->label('موبایل'),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت')
                    ->schema([
                        Select::make('employment_status')
                            ->label('وضعیت استخدام')
                            ->options([
                                'active' => 'فعال',
                                'on_leave' => 'مرخصی',
                                'suspended' => 'تعلیق',
                                'terminated' => 'پایان همکاری',
                                'retired' => 'بازنشسته',
                            ])
                            ->default('active'),

                        Select::make('clearance_level')
                            ->label('سطح محرمانگی')
                            ->options([
                                'public' => 'عمومی',
                                'internal' => 'داخلی',
                                'confidential' => 'محرمانه',
                                'secret' => 'سری',
                            ])
                            ->default('internal'),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('کد پرسنلی')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('نام کامل')
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('primaryPosition.title')
                    ->label('پست')
                    ->searchable(),

                Tables\Columns\TextColumn::make('currentOrgUnit.name')
                    ->label('واحد')
                    ->searchable(),

                Tables\Columns\TextColumn::make('employment_status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'on_leave' => 'warning',
                        'suspended' => 'danger',
                        'terminated', 'retired' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('کاربر سامانه')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('employment_status')
                    ->label('وضعیت استخدام')
                    ->options([
                        'active' => 'فعال',
                        'on_leave' => 'مرخصی',
                        'suspended' => 'تعلیق',
                        'terminated' => 'پایان همکاری',
                        'retired' => 'بازنشسته',
                    ]),

                SelectFilter::make('current_org_unit_id')
                    ->label('واحد')
                    ->relationship('currentOrgUnit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
