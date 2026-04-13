<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $minute->minute_number }}</title>
    <style>
        @page { margin: 2cm; }
        body {
            font-family: 'Vazirmatn', 'Tahoma', sans-serif;
            direction: rtl;
            font-size: 12pt;
            line-height: 1.8;
            color: #1a1a1a;
        }
        .header {
            border-bottom: 2px solid #1e40af;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .header h1 {
            font-size: 18pt;
            color: #1e40af;
            margin: 0 0 0.5rem;
        }
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 1.5rem;
            border: 1px solid #d1d5db;
        }
        .meta-row { display: table-row; }
        .meta-cell {
            display: table-cell;
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
        }
        .meta-label { background: #f3f4f6; font-weight: bold; width: 30%; }
        .content { margin: 1.5rem 0; text-align: justify; }
        .key-decisions {
            background: #fef9c3;
            border-right: 4px solid #ca8a04;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        .key-decisions h3 { margin: 0 0 0.5rem; color: #854d0e; }
        .signatures {
            margin-top: 3rem;
            display: table;
            width: 100%;
        }
        .signature-cell {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 1rem;
        }
        .signature-line {
            border-top: 1px solid #6b7280;
            margin-top: 3rem;
            padding-top: 0.5rem;
        }
        .footer {
            position: fixed;
            bottom: 1cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 0.5rem;
        }
        .hash {
            font-family: monospace;
            font-size: 8pt;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>صورتجلسه</h1>
        <div>{{ $minute->minute_number }}</div>
    </div>

    <div class="meta">
        <div class="meta-row">
            <div class="meta-cell meta-label">عنوان</div>
            <div class="meta-cell">{{ $minute->title }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell meta-label">شماره جلسه</div>
            <div class="meta-cell">{{ $minute->meeting->meeting_number }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell meta-label">موضوع جلسه</div>
            <div class="meta-cell">{{ $minute->meeting->subject }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell meta-label">تاریخ جلسه</div>
            <div class="meta-cell">{{ $minute->meeting->scheduled_start?->format('Y/m/d H:i') ?? '—' }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell meta-label">نسخه</div>
            <div class="meta-cell">{{ $minute->current_version }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell meta-label">سطح محرمانگی</div>
            <div class="meta-cell">{{ $minute->confidentiality_level?->label() ?? '—' }}</div>
        </div>
    </div>

    @if ($minute->summary)
        <div>
            <h3>خلاصه</h3>
            <p>{{ $minute->summary }}</p>
        </div>
    @endif

    <div class="content">
        <h3>متن کامل</h3>
        {!! $minute->content_html !!}
    </div>

    @if (!empty($minute->key_decisions))
        <div class="key-decisions">
            <h3>تصمیمات کلیدی</h3>
            <ul>
                @foreach ($minute->key_decisions as $decision)
                    <li>{{ $decision }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="signatures">
        <div class="signature-cell">
            <div class="signature-line">
                <strong>دبیر جلسه</strong><br>
                {{ $minute->secretary?->full_name ?? '—' }}<br>
                @if ($minute->secretary_signed_at)
                    <small>امضا شده در {{ $minute->secretary_signed_at->format('Y/m/d H:i') }}</small>
                @else
                    <small>— امضا نشده —</small>
                @endif
            </div>
        </div>
        <div class="signature-cell">
            <div class="signature-line">
                <strong>رئیس جلسه</strong><br>
                {{ $minute->chairperson?->full_name ?? '—' }}<br>
                @if ($minute->chairperson_signed_at)
                    <small>امضا شده در {{ $minute->chairperson_signed_at->format('Y/m/d H:i') }}</small>
                @else
                    <small>— امضا نشده —</small>
                @endif
            </div>
        </div>
    </div>

    <div class="footer">
        کد یکپارچگی محتوا (SHA-256):
        <div class="hash">{{ $minute->getContentHash() }}</div>
        <div>تولید شده توسط سامانه مدیریت جلسات در {{ now()->format('Y/m/d H:i') }}</div>
    </div>
</body>
</html>
