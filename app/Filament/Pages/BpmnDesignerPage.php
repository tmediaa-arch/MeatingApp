<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Workflow\Actions\DeployProcessAction;
use App\Domains\Workflow\Services\ServiceTasks\ServiceTaskRegistry;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Designer گرافیکی BPMN با bpmn.js.
 *
 * شامل:
 *  - canvas برای طراحی graphical
 *  - properties panel سفارشی (با extensions mms:)
 *  - دکمه save که XML تولید می‌کند و از DeployProcessAction استفاده می‌کند
 */
class BpmnDesignerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.bpmn-designer';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'Designer BPMN';
    }

    public function getTitle(): string
    {
        return 'طراحی فرایند BPMN';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('اطلاعات اولیه')->schema([
                TextInput::make('process_key')
                    ->label('کلید فرایند')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('meeting_minute_approval'),
                TextInput::make('name')
                    ->label('نام')
                    ->required(),
                TextInput::make('category')
                    ->label('دسته')
                    ->datalist(['meeting', 'task', 'approval', 'system']),
                Toggle::make('publish_immediately')
                    ->label('انتشار فوری')
                    ->default(false),
            ])->columns(2),

            Hidden::make('bpmn_xml'),
        ])->statePath('data');
    }

    public function getServiceTasksJson(): string
    {
        /** @var ServiceTaskRegistry $registry */
        $registry = app(ServiceTaskRegistry::class);
        return json_encode(array_values($registry->metadata()), JSON_UNESCAPED_UNICODE);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (empty($data['bpmn_xml'])) {
            Notification::make()
                ->title('خطا')
                ->body('XML فرایند خالی است. ابتدا در Designer طراحی کنید.')
                ->danger()
                ->send();
            return;
        }

        try {
            $def = app(DeployProcessAction::class)->execute([
                'organization_id' => auth()->user()->employee?->organization_id,
                'process_key' => $data['process_key'],
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'bpmn_xml' => $data['bpmn_xml'],
                'publish_immediately' => (bool) ($data['publish_immediately'] ?? false),
                'creator_user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('فرایند با موفقیت deploy شد')
                ->body("نسخه: {$def->version}")
                ->success()
                ->send();

            $this->redirect(route('filament.admin.resources.process-definitions.view', $def));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطا در deploy')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
