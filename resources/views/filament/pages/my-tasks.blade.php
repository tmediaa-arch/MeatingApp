<x-filament-panels::page>
    <div class="flex gap-2 mb-4">
        <x-filament::tabs>
            <x-filament::tabs.item wire:click="switchTab('active')" :active="$activeTab === 'active'">
                فعال
            </x-filament::tabs.item>
            <x-filament::tabs.item wire:click="switchTab('overdue')" :active="$activeTab === 'overdue'">
                تأخیردار
            </x-filament::tabs.item>
            <x-filament::tabs.item wire:click="switchTab('completed')" :active="$activeTab === 'completed'">
                تکمیل‌شده
            </x-filament::tabs.item>
            <x-filament::tabs.item wire:click="switchTab('all')" :active="$activeTab === 'all'">
                همه
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
