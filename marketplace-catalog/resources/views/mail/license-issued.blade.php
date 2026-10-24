<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #303030; line-height: 1.5;">
    <p>Thank you for your purchase of <strong>{{ $order->package->name }}</strong>.</p>
    <p>Your license key:</p>
    <p style="font-family: monospace; font-size: 16px; background: #f1f1f1; padding: 10px 14px; border-radius: 6px; display: inline-block;">{{ $order->license?->key }}</p>
    <p>Enter it in your store under <strong>Admin → Marketplace</strong> to install the package. Each key is active on one store at a time; you can move it from your account at {{ url('/account') }}.</p>
    <p>Order #{{ $order->id }} · {{ $order->amount }} {{ $order->currency }}</p>
</body>
</html>
