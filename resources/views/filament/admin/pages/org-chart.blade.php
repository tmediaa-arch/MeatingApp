<x-filament-panels::page>
    <div class="space-y-4" dir="rtl">
        {{-- انتخاب سازمان --}}
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                انتخاب سازمان
            </label>
            <select wire:model.live="selectedOrganization"
                class="block w-full max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                @foreach ($this->getOrganizations() as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-gray-500">برای ویرایش هر واحد، روی کارت آن کلیک کنید.</p>
        </div>

        {{-- درخت سازمانی --}}
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6 overflow-x-auto">
            @php $tree = $this->getTreeData(); @endphp

            @if (empty($tree))
                <div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400">هیچ واحدی برای این سازمان تعریف نشده است.</p>
                </div>
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
            margin: 0;
            padding: 0;
        }
        .org-tree ul {
            padding-right: 2rem;
            position: relative;
        }
        .org-tree ul::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 1.4rem;
            right: 0.85rem;
            width: 2px;
            background: rgb(203 213 225);
        }
        .dark .org-tree ul::before { background: rgb(55 65 81); }

        .org-tree li {
            position: relative;
            margin: 0.4rem 0;
        }
        .org-tree ul > li::before {
            content: '';
            position: absolute;
            top: 1.4rem;
            right: -1.15rem;
            width: 1.15rem;
            height: 2px;
            background: rgb(203 213 225);
        }
        .dark .org-tree ul > li::before { background: rgb(55 65 81); }

        .org-node {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.85rem;
            background: rgb(249 250 251);
            border: 1px solid rgb(229 231 235);
            border-right-width: 4px;
            border-radius: 0.6rem;
            min-width: 260px;
            max-width: 400px;
            text-decoration: none;
            transition: all 0.12s ease-in-out;
        }
        .org-node:hover {
            background: rgb(238 242 255);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(-2px);
        }
        .dark .org-node {
            background: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }
        .dark .org-node:hover { background: rgb(49 46 79); }

        .org-node-avatar {
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: white;
        }
        .org-node-body { flex: 1; min-width: 0; }
        .org-node-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: rgb(17 24 39);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .dark .org-node-name { color: rgb(243 244 246); }
        .org-node-badge {
            font-size: 0.65rem;
            padding: 0.05rem 0.45rem;
            border-radius: 9999px;
            background: rgb(224 231 255);
            color: rgb(55 48 163);
            font-weight: 600;
        }
        .org-node-meta {
            font-size: 0.72rem;
            color: rgb(107 114 128);
            margin-top: 0.15rem;
        }
        .org-node-count {
            flex-shrink: 0;
            text-align: center;
            font-size: 0.7rem;
            color: rgb(107 114 128);
        }
        .org-node-count strong {
            display: block;
            font-size: 1rem;
            color: rgb(37 99 235);
        }
        .org-edit-hint {
            opacity: 0;
            font-size: 0.65rem;
            color: rgb(99 102 241);
            transition: opacity 0.12s;
        }
        .org-node:hover .org-edit-hint { opacity: 1; }
    </style>
</x-filament-panels::page>
