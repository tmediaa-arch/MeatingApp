<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Filament\Resources\ProcessDefinitionResource\Pages;
use App\Filament\Resources\ProcessDefinitionResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcessDefinitionResource extends Resource
{
    protected static ?string $model = ProcessDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'فرایند';
    }

    public static function getPluralModelLabel(): string
    {
        return 'فرایندها';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات فرایند')->schema([
                Forms\Components\TextInput::make('process_key')
                    ->label('کلید فرایند')
                    ->required()
                    ->maxLength(100)
                    ->helperText('کلید یکتا — مثلاً meeting_minute_approval'),

                Forms\Components\TextInput::make('name')
                    ->label('نام')
                    ->required(),

                Forms\Components\TextInput::make('category')
                    ->label('دسته')
                    ->datalist(['meeting', 'task', 'approval', 'system'])
                    ->maxLength(50),

                Forms\Components\Textarea::make('description')
                    ->label('شرح')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('BPMN XML')->schema([
                Forms\Components\Textarea::make('bpmn_xml')
                    ->label('محتوای BPMN 2.0 XML')
                    ->required()
                    ->rows(20)
                    ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 11px;'])
                    ->columnSpanFull()
                    ->helperText('شما می‌توانید از Designer گرافیکی استفاده کنید یا XML را مستقیماً paste کنید.'),
            ]),

            Forms\Components\Section::make('انتشار')->schema([
                Forms\Components\Toggle::make('publish_immediately')
                    ->label('انتشار فوری پس از deploy')
                    ->dehydrated(false)
                    ->visible(fn ($context) => $context === 'create')
                    ->default(false),
            ]),
        ]);
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
                    ->badge()
                    ->color(fn (ProcessDefinitionStatus $s) => $s->color())
                    ->formatStateUsing(fn (ProcessDefinitionStatus $s) => $s->label()),

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
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ProcessDefinitionStatus::options()),

                Tables\Filters\Filter::make('latest_only')
                    ->label('فقط آخرین نسخه')
                    ->query(fn (Builder $q) => $q->where('is_latest', true))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Draft),

                Tables\Actions\Action::make('publish')
                    ->label('انتشار')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (ProcessDefinition $r) {
                        // unset previous latest
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

                Tables\Actions\Action::make('deprecate')
                    ->label('منسوخ')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (ProcessDefinition $r) => $r->status === ProcessDefinitionStatus::Published)
                    ->requiresConfirmation()
                    ->action(fn (ProcessDefinition $r) => $r->update([
                        'status' => ProcessDefinitionStatus::Deprecated,
                        'is_latest' => false,
                    ])),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ElementsRelationManager::class,
            RelationManagers\InstancesRelationManager::class,
        ];
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
