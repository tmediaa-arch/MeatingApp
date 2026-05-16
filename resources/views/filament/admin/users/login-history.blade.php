@php
    $resultLabels = [
        'success' => ['ورود موفق', 'text-success-600'],
        'failed_credentials' => ['ورود ناموفق', 'text-danger-600'],
        'locked' => ['حساب قفل‌شده', 'text-warning-600'],
        'mfa_required' => ['نیازمند تأیید دومرحله‌ای', 'text-gray-500'],
        'mfa_failed' => ['تأیید دومرحله‌ای ناموفق', 'text-danger-600'],
        'disabled' => ['حساب غیرفعال', 'text-warning-600'],
        'expired' => ['حساب منقضی', 'text-warning-600'],
    ];
@endphp

<div class="space-y-2" dir="rtl">
    @forelse ($logs as $log)
        @php [$label, $color] = $resultLabels[$log->result] ?? [$log->result, 'text-gray-500']; @endphp
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-xs">
            <div class="flex items-center justify-between">
                <span class="font-bold {{ $color }}">{{ $label }}</span>
                <span class="text-gray-500">
                    {{ $log->performed_at ? \Morilog\Jalali\Jalalian::fromCarbon($log->performed_at)->format('Y/m/d H:i') : '—' }}
                </span>
            </div>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-gray-500">
                <span>IP: {{ $log->ip_address ?? '—' }}</span>
                <span>روش: {{ $log->auth_method ?? '—' }}</span>
                @if ($log->logged_out_at)
                    <span>خروج: {{ \Morilog\Jalali\Jalalian::fromCarbon($log->logged_out_at)->format('Y/m/d H:i') }}
                        ({{ $log->logout_reason ?? 'user' }})</span>
                @endif
            </div>
            @if ($log->user_agent)
                <div class="mt-1 truncate text-gray-400" title="{{ $log->user_agent }}">
                    {{ \Illuminate\Support\Str::limit($log->user_agent, 90) }}
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">فعالیت ورودی برای این کاربر ثبت نشده است.</p>
    @endforelse
</div>
