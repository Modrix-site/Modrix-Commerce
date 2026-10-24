<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you · {{ $order->package->name }}</title>
    <link rel="stylesheet" href="{{ asset('marketplace.css') }}">
    @if ($order->status !== 'paid')
        {{-- The license appears once Stripe confirms the payment. --}}
        <meta http-equiv="refresh" content="4">
    @endif
</head>
<body>
    <main style="max-width: 640px; margin: 48px auto; padding: 0 16px;">
        <h1>{{ $order->status === 'paid' ? 'Payment received' : 'Confirming your payment…' }}</h1>
        <p>{{ $order->package->name }} · {{ $order->amount }} {{ $order->currency }}</p>
        @if ($order->status === 'paid' && $order->license)
            <p>Your license key:</p>
            <p><code style="font-size: 18px;">{{ $order->license->key }}</code></p>
            <p>Enter it in your store under Admin → Marketplace. It is also in your <a href="{{ url('/account') }}">account</a> and your email.</p>
        @elseif ($order->status === 'expired')
            <p>This checkout expired. <a href="{{ url('/item/'.$order->package->slug) }}">Try again</a>.</p>
        @else
            <p>This page refreshes by itself. It usually takes a few seconds.</p>
        @endif
    </main>
</body>
</html>
