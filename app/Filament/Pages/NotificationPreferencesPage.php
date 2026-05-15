<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Domains\Notifications\Models\NotificationTemplate;
use Filament\Forms;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class NotificationPreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBellSnooze;
    protected static ?string $navigationLabel = 'تنظیمات اعلان من';
    protected static ?string $title = 'تنظیمات اعلان من';
    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.notification-preferences';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        $prefs = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('template_key');

        $this->data['preferences'] = NotificationTemplate::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->map(function ($template) use ($prefs) {
                $pref = $prefs->get($template->key);
                $row = [
                    'template_key' => $template->key,
                    'template_name' => $template->name,
                    'category' => $template->category,
                ];
                foreach ($template->available_channels ?? [] as $ch) {
                    $row["ch_{$ch}"] = $pref ? in_array($ch, $pref->enabled_channels ?? []) : true;
                }
                return $row;
            })
            ->toArray();

        $userPref = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereNull('template_key')
            ->first();

        $this->data['quiet_hours_enabled'] = $userPref?->quiet_hours_enabled ?? false;
        $this->data['quiet_hours_start'] = $userPref?->quiet_hours_start ?? '22:00';
        $this->data['quiet_hours_end'] = $userPref?->quiet_hours_end ?? '07:00';

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ساعات سکوت')
                    ->description('در این ساعات اعلان‌های غیر بحرانی به بعد موکول می‌شوند.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('quiet_hours_enabled')->label('فعال'),
                        TimePicker::make('quiet_hours_start')->label('از'),
                        TimePicker::make('quiet_hours_end')->label('تا'),
                    ]),

                Section::make('کانال‌های اعلان به ازای هر نوع')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->content('برای هر نوع اعلان مشخص کنید از طریق چه کانال‌هایی می‌خواهید دریافت کنید.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $user = auth()->user();

        DB::transaction(function () use ($user) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'template_key' => null],
                [
                    'quiet_hours_enabled' => $this->data['quiet_hours_enabled'] ?? false,
                    'quiet_hours_start' => $this->data['quiet_hours_start'] ?? null,
                    'quiet_hours_end' => $this->data['quiet_hours_end'] ?? null,
                ],
            );

            foreach ($this->data['preferences'] ?? [] as $row) {
                $enabled = [];
                foreach (NotificationChannel::cases() as $ch) {
                    if (!empty($row["ch_{$ch->value}"])) {
                        $enabled[] = $ch->value;
                    }
                }

                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'template_key' => $row['template_key']],
                    ['enabled_channels' => $enabled],
                );
            }
        });

        Notification::make()
            ->success()
            ->title('تنظیمات ذخیره شد')
            ->send();
    }
}
