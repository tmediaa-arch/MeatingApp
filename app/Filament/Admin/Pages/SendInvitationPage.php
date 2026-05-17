<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Identity\Services\UserInvitationService;
use App\Support\Mobile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * صفحهٔ ارسال دعوت‌نامهٔ ورود — یک لینک دعوت برای شمارهٔ موبایل پیامک می‌شود.
 */
class SendInvitationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;
    protected static string|\UnitEnum|null $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 30;
    protected string $view = 'filament.admin.pages.send-invitation';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'ارسال دعوت‌نامه';
    }

    public function getTitle(): string
    {
        return 'ارسال دعوت‌نامهٔ ورود';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('user.create') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات دعوت‌شونده')
                    ->description('یک پیامک حاوی لینک دعوت برای این شماره ارسال می‌شود. در صورت نبودِ حساب، هنگام کلیک روی لینک حساب ساخته خواهد شد.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mobile')
                            ->label('شمارهٔ موبایل')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->placeholder('09xxxxxxxxx')
                            ->columnSpanFull(),
                        TextInput::make('first_name')->label('نام')->maxLength(100),
                        TextInput::make('last_name')->label('نام خانوادگی')->maxLength(100),
                    ]),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $mobile = Mobile::normalize($data['mobile'] ?? null);

        if ($mobile === null) {
            Notification::make()
                ->title('شمارهٔ موبایل معتبر نیست')
                ->danger()
                ->send();

            return;
        }

        try {
            app(UserInvitationService::class)->createAndSend(
                $mobile,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                auth()->user(),
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('ارسال دعوت‌نامه ناموفق بود')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('دعوت‌نامه ارسال شد')
            ->body("لینک دعوت به شمارهٔ {$mobile} پیامک شد.")
            ->success()
            ->send();

        $this->form->fill();
    }
}
