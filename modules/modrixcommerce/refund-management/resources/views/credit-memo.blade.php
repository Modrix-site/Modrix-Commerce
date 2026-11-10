@extends('documents.layout')

@section('content')
    <div class="doc-header">
        <h1>{{ $heading }}</h1>
        <div>{{ $number }}</div>
    </div>
    @php($addr = $memo->buyer['address'] ?? null)
    <table class="grid meta" style="table-layout: fixed;">
        <tr>
            <td style="width: 50%; padding-right: 8mm; word-wrap: break-word;">
                <div class="label">Credited to</div>
                @if ($addr)
                    <div><strong>{{ $addr['first_name'] }} {{ $addr['last_name'] }}</strong></div>
                    @if (! empty($addr['company']))<div>{{ $addr['company'] }}</div>@endif
                    <div>{{ $addr['line1'] }}</div>
                    @if (! empty($addr['line2']))<div>{{ $addr['line2'] }}</div>@endif
                    <div>{{ $addr['city'] }}@if (! empty($addr['region'])), {{ $addr['region'] }}@endif {{ $addr['postal_code'] ?? '' }}</div>
                    <div>{{ $addr['country_name'] ?? $addr['country_code'] }}</div>
                @endif
                <div>{{ $memo->buyer['email'] ?? $order->email }}</div>
                @if (! empty($memo->buyer['phone']))<div>{{ $memo->buyer['phone'] }}</div>@endif
            </td>
            <td style="width: 50%; word-wrap: break-word;">
                <div class="label">Credit memo details</div>
                <div>Issued: {{ $memo->issued_at->format('Y-m-d') }}</div>
                <div>Order: {{ $order->number }}</div>
                @if ($order->invoice)<div>Original Invoice: {{ $order->invoice->number }}</div>@endif
                <div>Currency: {{ $memo->currency }}</div>
                @if ($track)
                    <div>Refund channel: {{ \EcoModular\RefundManagement\Models\RefundPaymentTrack::CHANNELS[$track->channel] ?? $track->channel }}</div>
                    <div>Payout status: {{ \EcoModular\RefundManagement\Models\RefundPaymentTrack::STATUSES[$track->payout_status] ?? $track->payout_status }}</div>
                    @if ($track->reference)<div>Reference: {{ $track->reference }}</div>@endif
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Tax</th>
                <th class="num">Credited</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memo->lines as $line)
                <tr>
                    <td>
                        {{ $line['name'] }}
                        @if (! empty($line['variant']))
                            <div class="muted">{{ $line['variant'] }}</div>
                        @endif
                        @if (! empty($line['restocked']))
                            <div class="muted">Restocked to inventory</div>
                        @endif
                    </td>
                    <td>{{ $line['sku'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $line['unit_price'] }}</td>
                    <td class="num">{{ $line['tax'] }}</td>
                    <td class="num">{{ $line['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php($t = $memo->totals)
    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="num">{{ $t['subtotal'] }}</td></tr>
        @if (! empty($t['shipping_refund']))
            <tr><td class="muted">Shipping refunded</td><td class="num">{{ $t['shipping_refund'] }}</td></tr>
        @endif
        <tr>
            <td class="muted">{{ ! empty($t['prices_include_tax']) ? 'Tax (included)' : 'Tax' }}</td>
            <td class="num">{{ $t['tax_total'] }}</td>
        </tr>
        @if (! empty($t['adjustment_amount']))
            <tr><td class="muted">Adjustment</td><td class="num">{{ $t['adjustment_amount'] }}</td></tr>
        @endif
        <tr class="grand"><td>Total credited</td><td class="num">{{ $t['grand_total'] }}</td></tr>
    </table>

    @if (! empty($memo->notes))
        <div class="box">
            <div class="label">Refund reason / notes</div>
            <div>{{ $memo->notes }}</div>
        </div>
    @endif
@endsection
