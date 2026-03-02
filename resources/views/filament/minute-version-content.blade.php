{{-- نمایش جزئیات یک نسخه از صورتجلسه --}}

<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-semibold text-gray-600">شماره نسخه:</span>
            <span class="ml-2">{{ $version->version_number }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-600">تاریخ ایجاد:</span>
            <span class="ml-2">{{ $version->created_at->format('Y/m/d H:i') }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-600">ایجاد توسط:</span>
            <span class="ml-2">{{ $version->creator?->name ?? '—' }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-600">Hash محتوا:</span>
            <span class="ml-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($version->content_hash, 16) }}</span>
        </div>
    </div>

    @if ($version->change_summary)
        <div>
            <h4 class="font-semibold text-gray-700 mb-2">خلاصه تغییر</h4>
            <p class="text-sm bg-gray-50 dark:bg-gray-800 p-3 rounded">
                {{ $version->change_summary }}
            </p>
        </div>
    @endif

    <div>
        <h4 class="font-semibold text-gray-700 mb-2">محتوای نسخه</h4>
        <div class="prose dark:prose-invert max-w-none border rounded p-4 bg-white dark:bg-gray-900">
            {!! $version->content_html !!}
        </div>
    </div>
</div>
