<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @php($branding = $page['props']['branding'] ?? null)
        @if (! empty($branding['favicon']))
            <link rel="icon" href="{{ $branding['favicon'] }}">
            <link rel="apple-touch-icon" href="{{ $branding['favicon'] }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif
        @if (! empty($branding['accent']) && preg_match('/^#[0-9a-fA-F]{6}$/', $branding['accent']))
            <style>:root { --sidebar-primary: {{ $branding['accent'] }}; --sidebar-brand: {{ $branding['accent'] }}; }</style>
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', \App\Support\PageEntry::for($page['component'])])
        <x-inertia::head>
            <title>{{ ! empty($branding['panel_name']) ? $branding['panel_name'] : config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
        {{ \App\Support\CustomCode::for($page['component'], 'head') }}
    </head>
    <body class="font-sans antialiased">
        @if (request()->attributes->get('maintenance_preview') && str_starts_with($page['component'], 'storefront/'))
            <div role="status" style="position:sticky;top:0;z-index:100;padding:8px 16px;background:#b45309;color:#fff;font:600 13px/1.4 system-ui,sans-serif;text-align:center">
                Maintenance mode is on: shoppers see the maintenance page. You can see the store because you are staff or on an allowed address.
            </div>
        @endif
        <x-inertia::app />
        {{ \App\Support\CustomCode::for($page['component'], 'body') }}
    </body>
</html>
