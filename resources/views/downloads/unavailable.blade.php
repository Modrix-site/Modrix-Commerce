<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Download unavailable') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f8f8f7; color: #1c1c1c; }
        main { max-width: 32rem; margin: 15vh auto; padding: 2rem 1.5rem; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 .75rem; }
        p { line-height: 1.5; margin: 0 0 1rem; color: #4a4a4a; }
        a { color: inherit; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('This download link has expired or is no longer available.') }}</h1>
    <p>{{ __('Links stop working after their download limit or expiry date, or when an order is cancelled or refunded.') }}</p>
    <p>{{ __('If you bought this item, find it in your orders or contact us and we will help.') }}</p>
    <p><a href="{{ url('/account/orders') }}">{{ __('Go to my orders') }}</a></p>
</main>
</body>
</html>
