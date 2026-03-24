<x-filament-panels::page>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-3xl font-bold text-blue-600">{{ $running }}</div>
            <div class="text-sm text-gray-600">در حال اجرا</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-amber-600">{{ $suspended }}</div>
            <div class="text-sm text-gray-600">متوقف</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-green-600">{{ $completed_today }}</div>
            <div class="text-sm text-gray-600">تکمیل امروز</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-red-600">{{ $failed_today }}</div>
            <div class="text-sm text-gray-600">شکست امروز</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-red-700">{{ $sla_breached }}</div>
            <div class="text-sm text-gray-600">SLA رد شده</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-red-700">{{ $open_incidents }}</div>
            <div class="text-sm text-gray-600">Incident باز</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-amber-700">{{ $open_user_tasks }}</div>
            <div class="text-sm text-gray-600">UserTask باز</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-3xl font-bold text-red-600">{{ $overdue_user_tasks }}</div>
            <div class="text-sm text-gray-600">UserTask overdue</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Incidentهای اخیر">
        @if ($recent_incidents->isEmpty())
            <p class="text-gray-500 text-sm">هیچ Incident بازی وجود ندارد.</p>
        @else
            <div class="space-y-2">
                @foreach ($recent_incidents as $inc)
                    <div class="flex items-center justify-between p-3 border rounded-lg">
                        <div>
                            <span class="inline-block px-2 py-1 text-xs bg-red-100 text-red-700 rounded">
                                {{ $inc->incident_type }}
                            </span>
                            <span class="text-sm">{{ Str::limit($inc->message, 80) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $inc->created_at->diffForHumans() }}
                            <a href="{{ route('filament.admin.resources.process-instances.view', $inc->instance) }}"
                               class="text-blue-500 hover:underline mr-2">
                                instance
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
