<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\ProcessDefinitionResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcessDefinitionResource extends Resource
{
    protected static ?string $model = ProcessDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'فرایند';
    }

    public static function getPluralModelLabel(): string
    {
        return 'فرایندها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات فرایند')
                    ->columns(2)
                    ->schema([
                        TextInput::make('process_key')
                            ->label('کلید فرایند')
                            ->required()
                            ->maxLength(100)
                            ->helperText('کلید یکتا — مثلاً meeting_minute_approval'),

                        TextInput::make('name')
                            ->label('نام')
                            ->required(),

                        TextInput::make('category')
                            ->label('دسته')
                            ->datalist(['meeting', 'task', 'approval', 'system'])
                            ->maxLength(50),

                        Textarea::make('description')
                            ->label('شرح')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('BPMN XML')->schema([
                    Textarea::make('bpmn_xml')
                        ->label('محتوای BPMN 2.0 XML')
                        ->required()
                        ->rows(20)
                        ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 11px;'])
                        ->columnSpanFull()
                        ->helperText('شما می‌توانید از Designer گرافیکی استفاده کنید یا XML را مستقیماً paste کنید.'),
                ]),
            ],
            sidebar: [
                Section::make('انتشار')->schema([
                    Toggle::make('publish_immediately')
                        ->label('انتشار فوری پس از deploy')
                        ->dehydrated(false)
                        ->visible(fn ($context) => $context === 'create')
                        ->default(false),
                ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('process_key')
                    ->label('کلید')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('نسخه')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('category')
                    ->label('دسته')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_latest')
                    ->label('آخرین')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('انتشار')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('instances_count')
                    ->label('Instances')
                    ->counts('instances')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ProcessDefinitionStatus::class),

                Filter::make('latest_only')
                    ->label('فقط آخرین نسخه')
                    ->query(fn (Builder $q) => $q->where('is_latest', true))
                    ->default(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Draft),

                    Action::make('publish')
                        ->label('انتشار')
                        ->icon(Heroicon::OutlinedRocketLaunch)
                        ->color('success')
                        ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Draft)
                        ->requiresConfirmation()
                        ->action(function (ProcessDefinition $r) {
                            ProcessDefinition::where('organization_id', $r->organization_id)
                                ->where('process_key', $r->process_key)
                                ->where('id', '!=', $r->id)
                                ->update(['is_latest' => false]);

                            $r->update([
                                'status' => ProcessDefinitionStatus::Published,
                                'is_latest' => true,
                                'published_by_user_id' => auth()->id(),
                                'published_at' => now(),
                            ]);
                        }),

                    Action::make('deprecate')
                        ->label('منسوخ')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('warning')
                        ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Published)
                        ->requiresConfirmation()
                        ->action(fn (ProcessDefinition $r) => $r->update([
                            'status' => ProcessDefinitionStatus::Deprecated,
                            'is_latest' => false,
                        ])),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessDefinitions::route('/'),
            'create' => Pages\CreateProcessDefinition::route('/create'),
            'edit' => Pages\EditProcessDefinition::route('/{record}/edit'),
            'view' => Pages\ViewProcessDefinition::route('/{record}'),
        ];
    }
}
