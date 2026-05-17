<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ورود به سامانه')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 55%, #4f46e5 100%);
            padding: 1rem;
        }
        .auth-card {
            background: #fff;
            width: 100%;
            max-width: 380px;
            border-radius: 1rem;
            box-shadow: 0 30px 70px rgba(23, 16, 60, 0.35);
            padding: 2rem 1.75rem;
        }
        .auth-brand {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            font-weight: 800;
            color: #4338ca;
            text-align: center;
        }
        .auth-sub {
            margin: 0 0 1.5rem;
            font-size: 0.82rem;
            color: #6b7280;
            text-align: center;
        }
        label { display: block; font-size: 0.8rem; color: #374151; margin-bottom: 0.35rem; }
        input[type=text], input[type=tel] {
            width: 100%;
            padding: 0.65rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 0.6rem;
            font-family: inherit;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        input:focus { outline: 2px solid #6366f1; border-color: #6366f1; }
        .otp-input { text-align: center; letter-spacing: 0.4em; font-size: 1.3rem; }
        .btn {
            width: 100%;
            padding: 0.7rem;
            border: 0;
            border-radius: 0.6rem;
            background: #6366f1;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.75rem;
        }
        .btn:hover { background: #4f46e5; }
        .btn-link {
            background: none;
            border: 0;
            color: #6366f1;
            font-family: inherit;
            font-size: 0.82rem;
            cursor: pointer;
            padding: 0;
            margin-top: 0.9rem;
            text-decoration: underline;
        }
        .err { color: #dc2626; font-size: 0.78rem; margin: 0.25rem 0 0; }
        .ok { color: #059669; font-size: 0.82rem; margin: 0.5rem 0 0; text-align: center; }
        .muted { color: #6b7280; font-size: 0.8rem; text-align: center; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="auth-card">
        <h1 class="auth-brand">{{ config('app.name', 'سامانه مدیریت جلسات') }}</h1>
        @yield('content')
    </div>
</body>
</html>
