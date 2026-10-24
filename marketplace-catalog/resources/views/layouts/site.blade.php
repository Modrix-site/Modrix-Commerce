@php
    $catalog = app(\Marketplace\Services\Catalog::class);
    $name = $catalog->catalogName();
    $user = auth()->user();
    $bodyClass = $bodyClass ?? 'marketplace-body';
    $bare = $bare ?? false;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $name }}</title>
    <link rel="stylesheet" href="{{ asset('site.css') }}">
    <link rel="stylesheet" href="{{ asset('marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('account.css') }}">
    <link rel="stylesheet" href="{{ asset('catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('pages.css') }}">
    <script src="{{ asset('account.js') }}" defer></script>
    <script src="{{ asset('catalog.js') }}" defer></script>
</head>
<body class="{{ $bodyClass }}">
<header>
    <div class="wrap bar">
        <a class="brand" href="{{ url('/') }}">
            @if (\Marketplace\Models\Setting::get('logo'))
                <img class="brand-image" src="{{ url('/branding/logo') }}" alt="{{ $name }}">
            @else
                <span class="brand-mark" aria-hidden="true">e</span>{{ $name }}
            @endif
        </a>
        <nav>
            @if (! $bare && isset($counts))
                @foreach (['all' => 'All', 'theme' => 'Themes', 'module' => 'Extensions'] as $key => $label)
                    <a href="{{ url('/'.($key === 'all' ? '' : '?type='.$key)) }}" @if (($type ?? 'all') === $key) aria-current="page" @endif>{{ $label }} {{ $counts[$key] }}</a>
                @endforeach
            @endif
            @if ($user)
                <a href="{{ url('/account') }}">Account</a>
                @if ($user->is_admin)
                    <a href="{{ url('/admin') }}">Admin workspace</a>
                @endif
            @elseif (! $bare)
                <a href="{{ url('/account/login') }}">Log in</a>
            @endif
            @unless ($bare)
                <a href="{{ url('/submit') }}">Submit a package ↗</a><a href="{{ url('/submit/update') }}">Submit an update ↗</a>
            @endunless
        </nav>
    </div>
</header>
<main class="wrap">
    @if (session('notice'))
        <p class="notice">{{ session('notice') }}</p>
    @endif
    @if ($errors->any())
        <p class="notice" role="alert">{{ $errors->first() }}</p>
    @endif
    @yield('content')
</main>
<footer class="site-footer wrap">
    <a href="{{ url('/') }}">{{ $name }}</a>
    <span>The building blocks of a better store.</span>
    <a href="{{ url('/admin') }}">Marketplace admin ↗</a>
</footer>
</body>
</html>
