<x-filament-panels::page>
    @php
        $active = $this->getActiveContext();
        $received = $this->getDelegationsReceived();
        $given = $this->getDelegationsGiven();
    @endphp

    {{-- Active context banner --}}
    @if ($active)
        <div class="bg-amber-50 dark:bg-amber-900/30 border-r-4 border-amber-500 p-4 rounded-lg mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <strong class="text-amber-900 dark:text-amber-200">⚠ حالت نمایندگی فعال است</strong>
                    <p class="text-sm text-amber-800 dark:text-amber-300 mt-1">
                        در حال اقدام به نمایندگی از: <strong>{{ $active->delegator->resolved_display_name }}</strong>
                        | محدوده: {{ $active->scope }}
                    </p>
                </div>
                <button wire:click="stopActing"
                    class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm">
                    خروج از حالت نمایندگی
                </button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- تفویض‌های دریافت شده --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                تفویض‌های دریافت شده
            </h3>

            @if ($received->isEmpty())
                <p class="text-gray-500 text-center py-4">هیچ تفویضی دریافت نکرده‌اید.</p>
            @else
                <div class="space-y-3">
                    @foreach ($received as $delegation)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        از: {{ $delegation->delegator->resolved_display_name }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        محدوده: <span class="font-medium">{{ $delegation->scope }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $delegation->starts_at->format('Y/m/d') }} تا
                                        {{ $delegation->ends_at->format('Y/m/d') }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    @php
                                        $badgeClass = match ($delegation->status) {
                                            'active' => 'bg-green-100 text-green-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'expired' => 'bg-gray-100 text-gray-800',
                                            'revoked' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="inline-block px-2 py-1 text-xs rounded {{ $badgeClass }}">
                                        {{ $delegation->status }}
                                    </span>
                                </div>
                            </div>

                            @if ($delegation->status === 'active' && $delegation->isActive())
                                <button wire:click="startActingAs({{ $delegation->id }})"
                                    class="mt-3 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    اقدام به نمایندگی
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- تفویض‌های داده شده --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                تفویض‌های داده شده
            </h3>

            @if ($given->isEmpty())
                <p class="text-gray-500 text-center py-4">هیچ تفویضی نداده‌اید.</p>
            @else
                <div class="space-y-3">
                    @foreach ($given as $delegation)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                به: {{ $delegation->delegate->resolved_display_name }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                محدوده: <span class="font-medium">{{ $delegation->scope }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $delegation->starts_at->format('Y/m/d') }} تا
                                {{ $delegation->ends_at->format('Y/m/d') }}
                                | اقدامات: {{ $delegation->actions_count ?? 0 }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
