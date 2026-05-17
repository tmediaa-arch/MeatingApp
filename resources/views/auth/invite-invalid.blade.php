@extends('auth.layout')

@section('title', 'لینک دعوت نامعتبر')

@section('content')
    <p class="auth-sub">لینک دعوت</p>

    <p class="muted">
        {{ $reason ?? 'این لینک دعوت نامعتبر یا منقضی شده است. لطفاً از مدیر سامانه درخواست دعوت جدید کنید.' }}
    </p>

    <form method="GET" action="{{ route('auth.mobile.show') }}" class="center">
        <button type="submit" class="btn">ورود با موبایل</button>
    </form>
@endsection
