@extends('layouts.site', ['title' => 'Set up the marketplace', 'bare' => true, 'bodyClass' => 'marketplace-body auth-body'])

@section('content')
    <section class="auth-card">
        <span class="eyebrow">FIRST RUN</span>
        <h1>Create the admin account</h1>
        <p class="meta">This page works once. After the first admin exists it redirects to the admin sign-in.</p>
        <form method="post" action="{{ url('/admin/setup') }}" class="stack">
            @csrf
            <label>Name <input name="name" value="{{ old('name') }}" required maxlength="120"></label>
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Password (12+ characters) <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
            <button type="submit">Create admin</button>
        </form>
    </section>
@endsection
