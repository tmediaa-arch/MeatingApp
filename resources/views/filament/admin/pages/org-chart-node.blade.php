<li>
    <div class="org-node">
        <div class="org-node-name">
            {{ $node['name'] }}
            <span class="org-node-badge">{{ $node['type'] }}</span>
        </div>
        <div class="org-node-meta">
            کد: <code>{{ $node['code'] }}</code>
            @if ($node['manager'])
                | مدیر: {{ $node['manager'] }}
            @endif
            | کارمندان: <strong>{{ $node['employees_count'] }}</strong>
        </div>
    </div>

    @if (!empty($node['children']))
        <ul>
            @foreach ($node['children'] as $child)
                @include('filament.admin.pages.org-chart-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
