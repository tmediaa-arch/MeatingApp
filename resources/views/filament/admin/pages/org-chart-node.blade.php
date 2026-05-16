@php
    $palette = [
        'organization' => '#0ea5e9',
        'deputy'       => '#6366f1',
        'management'   => '#8b5cf6',
        'department'   => '#0d9488',
        'office'       => '#f59e0b',
        'team'         => '#10b981',
        'committee'    => '#ec4899',
    ];
    $color = $palette[$node['type_value'] ?? ''] ?? '#64748b';
    $initial = mb_substr(trim((string) $node['name']), 0, 1);
@endphp

<li>
    <a href="{{ $node['edit_url'] }}" class="org-node" style="border-right-color: {{ $color }}"
       title="ویرایش واحد «{{ $node['name'] }}»">
        <span class="org-node-avatar" style="background: {{ $color }}">{{ $initial }}</span>

        <span class="org-node-body">
            <span class="org-node-name">
                {{ $node['name'] }}
                <span class="org-node-badge">{{ $node['type'] }}</span>
            </span>
            <span class="org-node-meta">
                کد: <code>{{ $node['code'] }}</code>
                @if ($node['manager'])
                    &nbsp;|&nbsp; مدیر: {{ $node['manager'] }}
                @endif
            </span>
            <span class="org-edit-hint">برای ویرایش کلیک کنید ✎</span>
        </span>

        <span class="org-node-count">
            <strong>{{ $node['employees_count'] }}</strong>
            کارمند
        </span>
    </a>

    @if (!empty($node['children']))
        <ul>
            @foreach ($node['children'] as $child)
                @include('filament.admin.pages.org-chart-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
