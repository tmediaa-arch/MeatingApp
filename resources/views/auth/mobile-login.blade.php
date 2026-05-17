@extends('auth.layout')

@section('title', 'ورود با موبایل')

@section('content')
    <p class="auth-sub">برای ورود، شمارهٔ موبایل خود را وارد کنید تا کد تأیید پیامک شود.</p>

    <form method="POST" action="{{ route('auth.mobile.request') }}">
        @csrf
        <label for="mobile">شمارهٔ موبایل</label>
        <input type="tel" id="mobile" name="mobile" value="{{ old('mobile') }}"
               placeholder="09xxxxxxxxx" autofocus inputmode="numeric">
        @error('mobile')
            <p class="err">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn">ارسال کد تأیید</button>
    </form>
@endsection
