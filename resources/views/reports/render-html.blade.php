@php
    /** @var \App\Domains\Reports\Models\Report $report */
    /** @var \App\Domains\Reports\DTOs\ReportResult $result */
    $columns = $result->columns ?? [];
    $rows = $result->rows ?? [];
    $summary = $result->summary ?? [];
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->display_name ?? $report->key }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 24px; color: #222; }
        h1 { margin: 0 0 4px; font-size: 20px; color: #1e40af; }
        h3 { margin: 18px 0 6px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: right; font-size: 12px; }
        th { background: #f3f4f6; }
        .meta { color: #6b7280; font-size: 12px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>{{ $report->display_name ?? $report->key }}</h1>
    <div class="meta">تاریخ تولید: {{ now()->format('Y-m-d H:i') }}</div>

    @if (! empty($summary))
        <h3>خلاصه</h3>
        <table>
            <tbody>
                @foreach ($summary as $key => $value)
                    @if (is_scalar($value) || $value === null)
                        <tr>
                            <th>{{ $key }}</th>
                            <td>{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($rows) && ! empty($columns))
        <h3>داده‌ها</h3>
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col['label'] ?? $col['key'] ?? '' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $row = (array) $row; @endphp
                    <tr>
                        @foreach ($columns as $col)
                            @php $val = $row[$col['key'] ?? ''] ?? ''; @endphp
                            <td>{{ is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif (empty($summary))
        <p>داده‌ای برای نمایش وجود ندارد.</p>
    @endif
</body>
</html>
