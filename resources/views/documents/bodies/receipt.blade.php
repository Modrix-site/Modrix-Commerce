<table class="grid"><tr>
    <td style="width: 50%;">
        <div class="label">{{ __('Received from') }}</div>
        <div>{{ $order->email }}</div>
    </td>
    <td style="width: 50%; text-align: right;">
        <div class="label">{{ __('Amount received') }}</div>
        <div style="font-size: 16pt; font-weight: bold;">{{ $amount }}</div>
        <span class="badge">{{ __('Paid') }}</span>
    </td>
</tr></table>

<table class="lines">
    <tbody>
        <tr><td>{{ __('Order') }}</td><td class="num">{{ $order->number }}</td></tr>
        @if ($invoiceNumber)<tr><td>{{ __('Invoice number') }}</td><td class="num">{{ $invoiceNumber }}</td></tr>@endif
        <tr><td>{{ __('Payment method') }}</td><td class="num">{{ $order->payment_method_name }}</td></tr>
        @if ($payment->provider_reference || $payment->provider_payment_id)
            <tr><td>{{ __('Reference') }}</td><td class="num">{{ $payment->provider_reference ?? $payment->provider_payment_id }}</td></tr>
        @endif
        <tr><td>{{ __('Payment date') }}</td><td class="num">{{ $date }}</td></tr>
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('Order total') }}</td><td class="num">{{ $grandTotal }}</td></tr>
    <tr><td>{{ __('Paid to date') }}</td><td class="num">{{ $paidToDate }}</td></tr>
    @if ($refunded)<tr><td>{{ __('Refunded') }}</td><td class="num">{{ $refunded }}</td></tr>@endif
    <tr class="grand"><td>{{ __('Balance due') }}</td><td class="num">{{ $balance }}</td></tr>
</table>
