<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set up the marketplace</title>
    <link rel="stylesheet" href="{{ asset('site.css') }}">
    <link rel="stylesheet" href="{{ asset('pages.css') }}">
</head>
<body class="marketplace-body auth-body">
<main class="wrap">
    <section class="auth-card install-card">
        <span class="eyebrow">MARKETPLACE SETUP</span>
        @if ($errors->any())
            <p class="notice" role="alert">{{ $errors->first() }}</p>
        @endif
        @yield('content')
    </section>
</main>
</body>
</html>
