<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Organization\Models\Employee;
use App\Filament\Admin\Resources\EmployeeResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'ساختار سازمانی';
    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return 'کارمند';
    }

    public static function getPluralModelLabel(): string
    {
        return 'کارمندان';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات هویتی')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('سازمان')
                        ->relationship('organization', 'name')
                        ->required(),

                    Forms\Components\TextInput::make('employee_code')
                        ->label('کد پرسنلی')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),

                    Forms\Components\TextInput::make('national_code')
                        ->label('کد ملی')
                        ->length(10)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('first_name')->label('نام')->required(),
                    Forms\Components\TextInput::make('last_name')->label('نام خانوادگی')->required(),
                    Forms\Components\TextInput::make('father_name')->label('نام پدر'),

                    Forms\Components\Select::make('gender')
                        ->label('جنسیت')
                        ->options(['male' => 'مرد', 'female' => 'زن']),

                    Forms\Components\DatePicker::make('birth_date')->label('تاریخ تولد')->jalali(),
                ]),

            Forms\Components\Section::make('اطلاعات استخدامی')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('primary_position_id')
                        ->label('پست اصلی')
                        ->relationship('primaryPosition', 'title')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('current_org_unit_id')
                        ->label('واحد فعلی')
                        ->relationship('currentOrgUnit', 'name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('reports_to_employee_id')
                        ->label('گزارش‌دهی به')
                        ->relationship('reportsTo', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                        ->searchable(),

                    Forms\Components\Select::make('employment_status')
                        ->label('وضعیت استخدام')
                        ->options([
                            'active' => 'فعال',
                            'on_leave' => 'مرخصی',
                            'suspended' => 'تعلیق',
                            'terminated' => 'پایان همکاری',
                            'retired' => 'بازنشسته',
                        ])
                        ->default('active'),

                    Forms\Components\DatePicker::make('hire_date')->label('تاریخ استخدام')->jalali(),
                    Forms\Components\DatePicker::make('termination_date')->label('تاریخ پایان همکاری')->jalali(),
                ]),

            Forms\Components\Section::make('سطح محرمانگی و کاربر')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('clearance_level')
                        ->label('سطح محرمانگی')
                        ->options([
                            'public' => 'عمومی',
                            'internal' => 'داخلی',
                            'confidential' => 'محرمانه',
                            'secret' => 'سری',
                        ])
                        ->default('internal'),

                    Forms\Components\TextInput::make('work_email')->label('ایمیل کاری')->email(),
                    Forms\Components\TextInput::make('work_phone')->label('تلفن کاری'),
                    Forms\Components\TextInput::make('mobile')->label('موبایل'),
                ]),
        ]);
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
                Tables\Filters\SelectFilter::make('employment_status')
                    ->label('وضعیت استخدام')
                    ->options([
                        'active' => 'فعال',
                        'on_leave' => 'مرخصی',
                        'suspended' => 'تعلیق',
                        'terminated' => 'پایان همکاری',
                        'retired' => 'بازنشسته',
                    ]),

                Tables\Filters\SelectFilter::make('current_org_unit_id')
                    ->label('واحد')
                    ->relationship('currentOrgUnit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
