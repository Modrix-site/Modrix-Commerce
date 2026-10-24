@extends('layouts.site', ['title' => 'Log in', 'bodyClass' => 'marketplace-body auth-body'])

@section('content')
    <section class="auth-card">
        <span class="eyebrow">YOUR MARKETPLACE ACCOUNT</span>
        <h1>Log in</h1>
        <p class="meta">Manage license keys, connected stores, purchases and package submissions.</p>
        <form method="post" action="{{ url('/account/login') }}" class="stack">
            @csrf
            <input type="hidden" name="next" value="{{ $next }}">
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus></label>
            <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button type="submit">Log in</button>
        </form>
        <p class="meta">New here? <a href="{{ url('/account/register?next='.rawurlencode($next)) }}">Create an account</a></p>
    </section>
@endsection
