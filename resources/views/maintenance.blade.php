<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $headline }} · {{ $store }}</title>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; box-sizing: border-box;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #f6f6f8; color: #18181b; }
            main { max-width: 520px; text-align: center; }
            img { max-height: 56px; max-width: 220px; margin-bottom: 28px; }
            .store { font-size: 20px; font-weight: 700; margin-bottom: 28px; }
            h1 { font-size: 28px; line-height: 1.2; margin: 0 0 14px; }
            p { margin: 0; line-height: 1.6; color: #52525b; white-space: pre-line; }
            .reopens { margin-top: 22px; font-size: 14px; }
            @media (prefers-color-scheme: dark) { body { background: #111114; color: #f4f4f5; } p { color: #a1a1aa; } }
        </style>
    </head>
    <body>
        <main>
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $store }}">
            @else
                <div class="store">{{ $store }}</div>
            @endif
            <h1>{{ $headline }}</h1>
            @if ($message !== '')
                <p>{{ $message }}</p>
            @endif
            @if ($reopens)
                <p class="reopens">We expect to be back on {{ $reopens->isoFormat('LLLL') }}.</p>
            @endif
        </main>
    </body>
</html>
