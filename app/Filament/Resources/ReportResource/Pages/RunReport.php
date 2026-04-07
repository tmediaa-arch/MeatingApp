<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportResource\Pages;

use App\Domains\Reports\Actions\RunReportAction;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\Report;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\ReportRunResource;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

/**
 * صفحه اجرای گزارش — فرم پارامترها به صورت پویا بر اساس input_schema ساخته می‌شود.
 */
class RunReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ReportResource::class;
    protected static string $view = 'filament.resources.report.run';

    public Report $record;
    public ?array $data = [];

    protected function getViewData(): array
    {
        return ['report' => $this->record];
    }

    public function mount(int|string $record): void
    {
        $this->record = Report::findOrFail($record);
        $this->form->fill([
            'format' => 'pdf',
            'force_fresh' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->buildDynamicFields())
            ->statePath('data');
    }

    private function buildDynamicFields(): array
    {
        $fields = [
            Forms\Components\Section::make($this->record->display_name)
                ->description($this->record->description)
                ->schema($this->buildSchemaFields()),

            Forms\Components\Section::make('تنظیمات خروجی')->schema([
                Forms\Components\Select::make('format')
                    ->label('فرمت خروجی')
                    ->options(collect($this->record->supported_formats ?? ['pdf'])
                        ->mapWithKeys(fn ($f) => [$f => strtoupper($f)]))
                    ->required()
                    ->default('pdf'),
                Forms\Components\Toggle::make('force_fresh')
                    ->label('اجبار به اجرای تازه (نادیده گرفتن Cache)'),
            ])->columns(2),
        ];

        return $fields;
    }

    private function buildSchemaFields(): array
    {
        $schema = $this->record->input_schema ?? [];
        $components = [];

        foreach ($schema as $key => $meta) {
            $type = $meta['type'] ?? 'text';
            $label = $meta['label'] ?? $key;
            $required = (bool) ($meta['required'] ?? false);
            $default = $meta['default'] ?? null;

            $component = match ($type) {
                'date' => Forms\Components\DatePicker::make($key),
                'datetime' => Forms\Components\DateTimePicker::make($key),
                'number' => Forms\Components\TextInput::make($key)->numeric(),
                'select' => Forms\Components\Select::make($key)
                    ->options(collect($meta['options'] ?? [])->mapWithKeys(fn ($o) => [$o => $o])),
                'organization' => Forms\Components\Select::make($key)
                    ->relationship('organization', 'display_name')
                    ->searchable(),
                'org_unit' => Forms\Components\Select::make($key)
                    ->options(\App\Domains\Organization\Models\OrgUnit::pluck('name', 'id'))
                    ->searchable(),
                default => Forms\Components\TextInput::make($key),
            };

            $component->label($label);
            if ($required) $component->required();
            if ($default !== null) $component->default($default);

            $components[] = $component;
        }

        return $components;
    }

    public function execute(): void
    {
        $action = app(RunReportAction::class);

        try {
            $data = $this->data;
            $format = ReportFormat::from($data['format'] ?? 'pdf');
            $forceFresh = (bool) ($data['force_fresh'] ?? false);

            // فیلدهای dynamic را به filters ببریم
            unset($data['format'], $data['force_fresh']);

            $params = [
                'organization_id' => $data['organization_id'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'filters' => array_diff_key($data, array_flip(['organization_id', 'date_from', 'date_to'])),
            ];

            $run = $action->execute($this->record, $params, auth()->user(), $format, $forceFresh);

            Notification::make()
                ->title('گزارش با موفقیت اجرا شد')
                ->body("اجرا #{$run->id} — {$run->row_count} رکورد")
                ->success()
                ->send();

            $this->redirect(ReportRunResource::getUrl('view', ['record' => $run]));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطا در اجرای گزارش')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('run')
                ->label('اجرای گزارش')
                ->icon('heroicon-o-play')
                ->action('execute')
                ->color('primary'),
        ];
    }
}
