<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Provider health summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($this->getProvidersHealth() as $p)
                <div class="fi-section bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $p['name'] }}</h3>
                        <span class="text-xs text-gray-500">{{ $p['driver'] }}</span>
                    </div>
                    <x-filament::badge :color="$p['color']" size="sm">
                        {{ $p['status'] }}
                    </x-filament::badge>
                    <div class="mt-2 text-xs text-gray-500">
                        اتاق فعال: {{ $p['active_rooms'] }} • آخرین بررسی: {{ $p['last_check'] }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Active rooms table --}}
        <div>
            <h2 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">اتاق‌های فعال</h2>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
