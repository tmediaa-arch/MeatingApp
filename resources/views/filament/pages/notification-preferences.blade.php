<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">تنظیمات تفصیلی به ازای هر نوع اعلان</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="p-2 text-right">نوع اعلان</th>
                            <th class="p-2">ایمیل</th>
                            <th class="p-2">پیامک</th>
                            <th class="p-2">داخل سیستم</th>
                            <th class="p-2">Push</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->data['preferences'] ?? [] as $i => $row)
                            <tr class="border-b">
                                <td class="p-2 text-right">
                                    <div class="font-medium">{{ $row['template_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['template_key'] }}</div>
                                </td>
                                <td class="p-2 text-center">
                                    <input type="checkbox" wire:model="data.preferences.{{ $i }}.ch_email" />
                                </td>
                                <td class="p-2 text-center">
                                    <input type="checkbox" wire:model="data.preferences.{{ $i }}.ch_sms" />
                                </td>
                                <td class="p-2 text-center">
                                    <input type="checkbox" wire:model="data.preferences.{{ $i }}.ch_in_app" />
                                </td>
                                <td class="p-2 text-center">
                                    <input type="checkbox" wire:model="data.preferences.{{ $i }}.ch_push" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="flex justify-end gap-2">
            <x-filament::button type="submit" color="primary">ذخیره تنظیمات</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
