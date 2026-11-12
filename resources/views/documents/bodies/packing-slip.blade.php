<style>
    .pack-box { border: 0.8pt solid #c5c9ce; padding: 4mm; }
    .pack-label { color: #4b5563; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.7pt; margin-bottom: 2mm; }
    .pack-address { font-size: 11pt; line-height: 1.6; }
    .pack-ref { font-size: 15pt; font-weight: bold; margin-bottom: 2mm; }
    .pack-info { width: 100%; border-collapse: collapse; margin: 0 0 5mm; }
    .pack-info td { vertical-align: top; }
    .pack-summary { background: #f1f3f5; padding: 3mm; margin: 5mm 0 2mm; font-weight: bold; }
    .pack-items { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .pack-items thead { display: table-header-group; }
    .pack-items tr { page-break-inside: avoid; }
    .pack-items th { background: #20262d; color: white; text-align: left; padding: 3mm 2mm; font-size: 8pt; }
    .pack-items td { padding: 4mm 2mm; border-bottom: 0.7pt solid #c5c9ce; vertical-align: top; overflow-wrap: anywhere; }
    .pack-items .center { text-align: center; }
    .pack-check { display: inline-block; width: 4mm; height: 4mm; border: 0.8pt solid #333; }
    .pack-signoff { margin-top: 7mm; border: 0.8pt solid #c5c9ce; padding: 4mm; page-break-inside: avoid; }
    .pack-signoff td { width: 50%; padding: 3mm 2mm; }
    .pack-note { margin-top: 5mm; padding: 3mm 4mm; border-left: 2pt solid #333; background: #f5f5f5; }
</style>
<table class="pack-info"><tr>
    <td style="width: 66%;">
        <div class="pack-ref">{{ $order->number }}</div>
        <div>{{ __('Order date') }}: {{ $date }}</div>
        <div>{{ __('Shipping method') }}: {{ $order->shipping_method_name ?: __('Not specified') }}</div>
        @if ($shipment?->carrier)<div>{{ __('Carrier') }}: {{ $shipment->carrier }}</div>@endif
        @if ($shipment?->tracking_number)<div>{{ __('Tracking number') }}: {{ $shipment->tracking_number }}</div>@endif
    </td>
    <td style="width: 34%; text-align: right;">
        <a href="{{ $orderUrl }}"><img src="{{ $orderQr }}" style="width: 30mm; height: 30mm;" alt="{{ __('Order QR code') }}"></a>
        <div style="font-size: 7pt;">{{ __('Scan to open order (staff login)') }}</div>
    </td>
</tr></table>
<table class="pack-info"><tr>
    <td style="width: 58%; padding-right: 4mm;">
        <div class="pack-box">
            <div class="pack-label">{{ __('Ship to') }}</div>
            <div class="pack-address">
                @if ($shipping)
                    @include('documents.partials.address', ['address' => $shipping])
                @else
                    {{ __('Shipping address not provided. Confirm before dispatch.') }}
                @endif
            </div>
        </div>
    </td>
    <td style="width: 42%;">
        <div class="pack-box">
            <div class="pack-label">{{ __('From') }}</div>
            <strong>{{ $seller['name'] }}</strong>
            @if ($seller['address'])<div class="pre">{{ $seller['address'] }}</div>@endif
            @if ($seller['email'])<div>{{ $seller['email'] }}</div>@endif
        </div>
    </td>
</tr></table>
<div class="pack-summary">{{ __('Contents') }} &nbsp; | &nbsp; {{ $order->items->count() }} {{ __('line items') }} &nbsp; | &nbsp; {{ $order->items->sum('quantity') }} {{ __('total units') }}</div>
<table class="pack-items">
    <thead><tr>
        <th style="width: 6%;">#</th>
        <th style="width: 44%;">{{ __('Item / variant') }}</th>
        <th style="width: 23%;">{{ __('SKU') }}</th>
        <th style="width: 12%;" class="center">{{ __('Qty') }}</th>
        <th style="width: 15%;" class="center">{{ __('Packed') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><strong>{{ $item->product_name }}</strong>@if ($item->variant_name)<div class="muted">{{ $item->variant_name }}</div>@endif</td>
            <td style="font-size: 8pt; word-wrap: break-word;">{{ $item->sku ?: '—' }}</td>
            <td class="center" style="font-size: 13pt;"><strong>{{ $item->quantity }}</strong></td>
            <td class="center"><span class="pack-check"></span></td>
        </tr>
    @endforeach
    </tbody>
</table>
@if ($order->customer_note)
    <div class="pack-note">
        <div class="pack-label">{{ __('Customer / delivery instructions') }}</div>
        <div class="pre">{{ $order->customer_note }}</div>
    </div>
@endif
<div class="pack-signoff">
    <div class="pack-label">{{ __('Packing verification') }}</div>
    <table style="width: 100%; border-collapse: collapse;"><tr>
        <td>{{ __('Packed by') }}: __________________</td><td>{{ __('Checked by') }}: __________________</td>
    </tr><tr>
        <td>{{ __('Date') }}: _______________________</td><td>{{ __('Package count') }}: ________________</td>
    </tr></table>
    <div style="margin-top: 3mm; font-size: 8pt;">{{ __('Verify quantities, variants and shipping address before sealing the package.') }}</div>
</div>
<div class="muted" style="margin-top: 4mm; font-size: 8pt;">{{ __('Packing slip only. This document is not an invoice or proof of payment.') }}</div>
