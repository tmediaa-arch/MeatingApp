<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Models\LoginLog;
use App\Domains\Audit\Models\SecurityEvent;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * نمایش KPI های اصلی سامانه در داشبورد.
 *
 * در فاز ۱ این widget آمار Identity/Organization/Audit را نشان می‌دهد.
 * در فازهای بعد، آمار جلسات، مصوبات و وظایف به آن افزوده می‌شود.
 */
class SystemOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // کاربران
        $totalUsers = User::count();
        $activeUsers = User::where('status', UserStatus::Active)->count();
        $lockedUsers = User::where('status', UserStatus::Locked)->orWhere('locked_until', '>', now())->count();

        // ساختار سازمانی
        $totalUnits = OrgUnit::where('is_active', true)->count();
        $totalEmployees = Employee::where('employment_status', 'active')->count();

        // امنیت
        $unreviewedSecurityEvents = SecurityEvent::whereNull('reviewed_at')->count();
        $failedLoginsLast24h = LoginLog::query()
            ->where('result', '!=', 'success')
            ->where('performed_at', '>=', now()->subDay())
            ->count();

        // تفویض‌های فعال
        $activeDelegations = UserDelegation::where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();

        return [
            Stat::make('کاربران فعال', $activeUsers)
                ->description("از مجموع {$totalUsers} کاربر")
                ->descriptionIcon(Heroicon::MiniUsers)
                ->color('success'),

            Stat::make('واحدهای سازمانی', $totalUnits)
                ->description("{$totalEmployees} کارمند فعال")
                ->descriptionIcon(Heroicon::MiniBuildingOffice2)
                ->color('info'),

            Stat::make('تفویض‌های فعال', $activeDelegations)
                ->description('تفویض اختیار جاری')
                ->descriptionIcon(Heroicon::MiniArrowUturnRight)
                ->color('warning'),

            Stat::make('کاربران قفل', $lockedUsers)
                ->description($lockedUsers > 0 ? 'نیاز به بررسی' : 'وضعیت طبیعی')
                ->descriptionIcon(Heroicon::MiniLockClosed)
                ->color($lockedUsers > 0 ? 'danger' : 'gray'),

            Stat::make('رویدادهای امنیتی نشده', $unreviewedSecurityEvents)
                ->description($unreviewedSecurityEvents > 0 ? 'نیاز به بررسی' : 'بدون رویداد')
                ->descriptionIcon(Heroicon::MiniExclamationTriangle)
                ->color($unreviewedSecurityEvents > 0 ? 'danger' : 'success'),

            Stat::make('تلاش ناموفق ۲۴ ساعت', $failedLoginsLast24h)
                ->description('Login های ناموفق')
                ->descriptionIcon(Heroicon::MiniShieldExclamation)
                ->color($failedLoginsLast24h > 50 ? 'danger' : ($failedLoginsLast24h > 10 ? 'warning' : 'gray')),
        ];
    }
}
