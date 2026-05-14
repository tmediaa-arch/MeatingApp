<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title ?? $report->key }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 24px; color: #222; }
        h1 { margin: 0 0 16px; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: right; font-size: 12px; }
        th { background: #f3f4f6; }
        .meta { color: #6b7280; font-size: 12px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>{{ $report->title ?? $report->key }}</h1>
    <div class="meta">
        تاریخ تولید: {{ now()->format('Y-m-d H:i') }}
    </div>

    @php
        $rows = method_exists($result, 'rows') ? $result->rows() : (property_exists($result, 'rows') ? $result->rows : []);
        $columns = method_exists($result, 'columns') ? $result->columns() : (property_exists($result, 'columns') ? $result->columns : []);
        if (empty($columns) && ! empty($rows)) {
            $first = is_array($rows[0] ?? null) ? $rows[0] : (array) ($rows[0] ?? []);
            $columns = array_keys($first);
        }
    @endphp

    @if (! empty($rows))
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $row = (array) $row; @endphp
                    <tr>
                        @foreach ($columns as $col)
                            <td>{{ $row[$col] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>داده‌ای برای نمایش وجود ندارد.</p>
    @endif
</body>
</html>
