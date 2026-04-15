<x-filament-panels::page>
    <div class="flex gap-2 mb-4">
        <x-filament::tabs>
            <x-filament::tabs.item
                wire:click="switchTab('unread')"
                :active="$activeTab === 'unread'"
            >
                خوانده‌نشده
                @php $unread = $this->getUnreadCount(); @endphp
                @if ($unread > 0)
                    <x-filament::badge color="danger" size="xs">{{ $unread }}</x-filament::badge>
                @endif
            </x-filament::tabs.item>

            <x-filament::tabs.item
                wire:click="switchTab('all')"
                :active="$activeTab === 'all'"
            >
                همه
            </x-filament::tabs.item>

            <x-filament::tabs.item
                wire:click="switchTab('archived')"
                :active="$activeTab === 'archived'"
            >
                بایگانی
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
