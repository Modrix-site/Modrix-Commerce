<table class="grid"><tr>
    <td style="width: 50%;">
        <div class="label">{{ __('From') }}</div>
        <div><strong>{{ $seller['name'] }}</strong></div>
        @if ($seller['address'])<div class="pre">{{ $seller['address'] }}</div>@endif
        @if ($seller['email'])<div>{{ $seller['email'] }}</div>@endif
        @if ($seller['tax_id'])<div>{{ __('Tax ID') }}: {{ $seller['tax_id'] }}</div>@endif
    </td>
    <td style="width: 50%;">
        <div class="label">{{ __('Bill to') }}</div>
        @if ($invoice->buyer['address'])
            @include('documents.partials.address', ['address' => $invoice->buyer['address']])
        @else
            <div>{{ $invoice->buyer['name'] }}</div>
        @endif
        <div>{{ $invoice->buyer['email'] }}</div>
    </td>
</tr></table>

<table class="grid"><tr>
    <td><div class="label">{{ __('Invoice number') }}</div>{{ $invoice->number }}</td>
    <td><div class="label">{{ __('Issue date') }}</div>{{ $date }}</td>
    <td><div class="label">{{ __('Order') }}</div>{{ $orderNumber }}</td>
    <td><div class="label">{{ __('Payment method') }}</div>{{ $invoice->totals['payment_method'] }}</td>
</tr></table>

<table class="lines">
    <thead><tr>
        <th>{{ __('Item') }}</th>
        <th>{{ __('SKU') }}</th>
        <th class="num">{{ __('Qty') }}</th>
        <th class="num">{{ __('Unit price') }}</th>
        <th class="num">{{ __('Tax') }}</th>
        <th class="num">{{ __('Total') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line['name'] }}@if ($line['variant'])<div class="muted">{{ $line['variant'] }}</div>@endif</td>
            <td class="muted">{{ $line['sku'] }}</td>
            <td class="num">{{ $line['quantity'] }}</td>
            <td class="num">{{ $money($line['unit_price']) }}</td>
            <td class="num">{{ $money($line['tax']) }}</td>
            <td class="num">{{ $money($line['total']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('Subtotal') }}</td><td class="num">{{ $money($invoice->totals['subtotal']) }}</td></tr>
    @if ((float) $invoice->totals['discount_total'] > 0)
        <tr><td>{{ __('Discount') }}</td><td class="num">−{{ $money($invoice->totals['discount_total']) }}</td></tr>
    @endif
    <tr><td>{{ __('Shipping') }}@if ($invoice->totals['shipping_method']) <span class="muted">({{ $invoice->totals['shipping_method'] }})</span>@endif</td><td class="num">{{ $money($invoice->totals['shipping_total']) }}</td></tr>
    <tr><td>{{ $invoice->totals['prices_include_tax'] ? __('Included tax') : __('Tax') }}</td><td class="num">{{ $money($invoice->totals['tax_total']) }}</td></tr>
    @if ((float) ($invoice->totals['payment_fee_total'] ?? 0) > 0)
        <tr><td>{{ __('Cash on delivery fee (collected by the courier)') }}</td><td class="num">{{ $money($invoice->totals['payment_fee_total']) }}</td></tr>
    @endif
    <tr class="grand"><td>{{ __('Total') }}</td><td class="num">{{ $money($invoice->totals['grand_total']) }}</td></tr>
</table>
