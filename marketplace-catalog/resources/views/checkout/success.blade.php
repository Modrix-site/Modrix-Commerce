<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you · {{ $order->package->name }}</title>
    <link rel="stylesheet" href="{{ asset('marketplace.css') }}">
    @if ($refresh)
        {{-- The license appears once Stripe confirms the payment. --}}
        <meta http-equiv="refresh" content="4;url={{ url('/orders/success?order='.$order->id.'&try='.($try + 1)) }}">
    @endif
</head>
<body>
    <main style="max-width: 640px; margin: 48px auto; padding: 0 16px;">
        <h1>{{ match ($order->status) { 'paid' => 'Payment received', 'refunded' => 'Order refunded', 'expired' => 'Checkout not completed', default => 'Confirming your payment…' } }}</h1>
        <p>{{ $order->package->name }} · {{ $order->amount }} {{ $order->currency }}</p>
        @if ($order->status === 'paid' && $order->license)
            <p>Your license key:</p>
            <p><code style="font-size: 18px;">{{ $order->license->key }}</code></p>
            <p>Enter it in your store under Admin → Marketplace. It is also in your <a href="{{ url('/account') }}">account</a> and your email.</p>
        @elseif ($order->status === 'expired')
            <p>This checkout expired or the payment failed. <a href="{{ url('/item/'.$order->package->slug) }}">Try again</a>.</p>
        @elseif ($order->status === 'refunded')
            <p>This order was refunded, so its license is no longer active.</p>
        @elseif ($refresh)
            <p>This page refreshes by itself. It usually takes a few seconds.</p>
        @else
            <p>Confirmation is taking longer than usual. Some payment methods take a few days; we email your license key as soon as the payment is confirmed. It will also appear in your <a href="{{ url('/account') }}">account</a>.</p>
        @endif
    </main>
</body>
</html>
