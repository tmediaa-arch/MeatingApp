@php
    /** @var \App\Domains\Dashboards\Models\Dashboard|null $currentDashboard */
    /** @var array $widgets */
    /** @var \Illuminate\Database\Eloquent\Collection $dashboards */
@endphp

<x-filament-panels::page>
    @if($dashboards->isEmpty())
        <div class="p-6 text-center text-gray-500 dark:text-gray-400">
            هیچ داشبوردی برای شما تعریف نشده است.
        </div>
    @else
        {{-- انتخاب داشبورد --}}
        <div class="flex flex-wrap gap-2 mb-6 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            @foreach($dashboards as $dash)
                <button
                    wire:click="switchDashboard({{ $dash->id }})"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition
                        @if($currentDashboard?->id === $dash->id)
                            bg-primary-600 text-white
                        @else
                            bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200
                        @endif"
                >
                    @if($dash->icon)
                        <x-filament::icon :icon="$dash->icon" class="w-4 h-4 inline ml-1" />
                    @endif
                    {{ $dash->display_name }}
                </button>
            @endforeach

            <button
                wire:click="refreshDashboard"
                class="mr-auto px-3 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200"
                title="به‌روزرسانی"
            >
                <x-filament::icon icon="heroicon-o-arrow-path" class="w-4 h-4 inline" />
            </button>
        </div>

        {{-- محتوای داشبورد --}}
        @if($currentDashboard && count($widgets) > 0)
            <div class="grid grid-cols-12 gap-4">
                @foreach($widgets as $entry)
                    @php
                        $w = $entry['widget'];
                        $data = $entry['data'];
                        $error = $entry['error'];
                        $colSpan = max(1, min(12, $w['width']));
                    @endphp

                    <div class="col-span-12 md:col-span-{{ $colSpan }} bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-3">
                            {{ $w['display_name'] }}
                        </h3>

                        @if($error)
                            <div class="text-xs text-red-500 p-2 bg-red-50 dark:bg-red-900/20 rounded">
                                خطا: {{ $error }}
                            </div>
                        @elseif($data && $data['type'] === 'stat')
                            @php $p = $data['payload']; @endphp
                            <div class="text-center">
                                <div class="text-3xl font-bold text-{{ $p['color'] ?? 'primary' }}-600">
                                    {{ number_format((float)$p['value'], strpos((string)$p['value'], '.') !== false ? 1 : 0) }}
                                    @if($p['unit'])<span class="text-sm font-normal mr-1">{{ $p['unit'] }}</span>@endif
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $p['label'] }}</div>
                                @if(isset($p['trend_percent']))
                                    <div class="text-xs mt-2 text-{{ $p['trend_percent'] >= 0 ? 'success' : 'danger' }}-600">
                                        {{ $p['trend_percent'] >= 0 ? '↑' : '↓' }} {{ abs($p['trend_percent']) }}%
                                    </div>
                                @endif
                            </div>
                        @elseif($data && $data['type'] === 'chart')
                            @php $p = $data['payload']; @endphp
                            <div class="text-xs text-gray-500">نمودار {{ $p['chart_type'] ?? 'bar' }}</div>
                            <pre class="text-xs mt-2 overflow-auto max-h-40">{{ json_encode($p['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @elseif($data && $data['type'] === 'list')
                            @php $p = $data['payload']; @endphp
                            <ul class="space-y-2 text-sm">
                                @foreach($p['items'] as $item)
                                    <li class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-2">
                                        <span>{{ $item['title'] ?? '—' }}</span>
                                        @if(isset($item['subtitle']))
                                            <span class="text-xs text-gray-500">{{ $item['subtitle'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($data && $data['type'] === 'table')
                            @php $p = $data['payload']; @endphp
                            <div class="text-xs text-gray-500">جدول {{ count($p['rows'] ?? []) }} ردیف</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 text-center text-gray-500">
                این داشبورد ویجتی ندارد.
            </div>
        @endif
    @endif
</x-filament-panels::page>
