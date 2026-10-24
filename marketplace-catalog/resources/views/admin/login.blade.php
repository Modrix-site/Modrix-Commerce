@extends('layouts.site', ['title' => 'Admin sign-in', 'bare' => true, 'bodyClass' => 'marketplace-body auth-body'])

@section('content')
    <section class="auth-card">
        <span class="eyebrow">MARKETPLACE ADMIN</span>
        <h1>Sign in</h1>
        <form method="post" action="{{ url('/admin/login') }}" class="stack">
            @csrf
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus></label>
            <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
            <button type="submit">Sign in</button>
        </form>
    </section>
@endsection
