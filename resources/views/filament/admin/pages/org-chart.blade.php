<x-filament-panels::page>
    <div class="space-y-4">
        {{-- انتخاب سازمان --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                انتخاب سازمان
            </label>
            <select wire:model.live="selectedOrganization"
                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                @foreach ($this->getOrganizations() as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- درخت --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
            @php $tree = $this->getTreeData(); @endphp

            @if (empty($tree))
                <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                    هیچ واحدی برای این سازمان تعریف نشده است.
                </p>
            @else
                <ul class="org-tree">
                    @foreach ($tree as $node)
                        @include('filament.admin.pages.org-chart-node', ['node' => $node])
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <style>
        .org-tree, .org-tree ul {
            list-style: none;
            padding-right: 1.5rem;
            margin: 0;
        }
        .org-node {
            padding: 0.5rem 0.75rem;
            margin: 0.25rem 0;
            background: rgb(243 244 246);
            border-right: 3px solid rgb(59 130 246);
            border-radius: 0.375rem;
            display: inline-flex;
            flex-direction: column;
            min-width: 200px;
        }
        .dark .org-node {
            background: rgb(55 65 81);
        }
        .org-node-name {
            font-weight: 600;
            color: rgb(31 41 55);
        }
        .dark .org-node-name {
            color: rgb(243 244 246);
        }
        .org-node-meta {
            font-size: 0.75rem;
            color: rgb(107 114 128);
            margin-top: 0.25rem;
        }
        .org-node-badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            background: rgb(219 234 254);
            color: rgb(30 64 175);
            font-size: 0.7rem;
            border-radius: 9999px;
            margin-right: 0.25rem;
        }
    </style>
</x-filament-panels::page>
