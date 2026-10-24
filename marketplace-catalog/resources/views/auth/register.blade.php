@extends('layouts.site', ['title' => 'Create an account', 'bodyClass' => 'marketplace-body auth-body'])

@section('content')
    <section class="auth-card">
        <span class="eyebrow">YOUR MARKETPLACE ACCOUNT</span>
        <h1>Create an account</h1>
        <p class="meta">Get license keys for free packages, buy premium ones, and publish your own.</p>
        <form method="post" action="{{ url('/account/register') }}" class="stack">
            @csrf
            <input type="hidden" name="next" value="{{ $next }}">
            <label>Name <input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" autofocus></label>
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Password <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <label>Confirm password <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <button type="submit">Create account</button>
        </form>
        <p class="meta">Already registered? <a href="{{ url('/account/login?next='.rawurlencode($next)) }}">Log in</a></p>
    </section>
@endsection
