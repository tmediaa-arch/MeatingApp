@extends('auth.layout')

@section('title', 'تأیید کد')

@section('content')
    <p class="auth-sub">کد تأیید پیامک‌شده به شمارهٔ <strong>{{ $mobile }}</strong> را وارد کنید.</p>

    <form method="POST" action="{{ route('auth.otp.verify') }}">
        @csrf
        <label for="code">کد تأیید</label>
        <input type="text" id="code" name="code" class="otp-input"
               autocomplete="one-time-code" inputmode="numeric" autofocus>
        @error('code')
            <p class="err">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn">ورود به سامانه</button>
    </form>

    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('auth.otp.resend') }}" class="center">
        @csrf
        <button type="submit" class="btn-link">ارسال مجدد کد</button>
    </form>

    <form method="GET" action="{{ route('auth.mobile.show') }}" class="center">
        <button type="submit" class="btn-link">تغییر شمارهٔ موبایل</button>
    </form>
@endsection
