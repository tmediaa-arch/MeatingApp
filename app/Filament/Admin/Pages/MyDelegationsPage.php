<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Identity\Services\DelegationContextService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class MyDelegationsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|\UnitEnum|null $navigationGroup = 'هویت و دسترسی';
    protected static ?int $navigationSort = 19;
    protected string $view = 'filament.admin.pages.my-delegations';

    public function getTitle(): string
    {
        return 'تفویض‌های من';
    }

    public static function getNavigationLabel(): string
    {
        return 'تفویض‌های من';
    }

    public function getDelegationsGiven(): \Illuminate\Database\Eloquent\Collection
    {
        return UserDelegation::query()
            ->where('delegator_user_id', auth()->id())
            ->with('delegate')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getDelegationsReceived(): \Illuminate\Database\Eloquent\Collection
    {
        return UserDelegation::query()
            ->where('delegate_user_id', auth()->id())
            ->with('delegator')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getActiveContext(): ?UserDelegation
    {
        return app(DelegationContextService::class)->getActiveDelegation();
    }

    public function startActingAs(int $delegationId): void
    {
        $delegation = UserDelegation::findOrFail($delegationId);

        try {
            app(DelegationContextService::class)
                ->startActingOnBehalfOf(auth()->user(), $delegation);

            Notification::make()
                ->title('حالت نمایندگی فعال شد')
                ->body('شما در حال اقدام به نمایندگی از ' . $delegation->delegator->resolved_display_name . ' هستید.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطا در فعال‌سازی حالت نمایندگی')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function stopActing(): void
    {
        app(DelegationContextService::class)->stopActingOnBehalfOf();

        Notification::make()
            ->title('بازگشت به حساب شخصی')
            ->success()
            ->send();
    }
}
