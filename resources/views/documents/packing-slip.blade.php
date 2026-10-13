@extends('documents.layout')

@section('content')
<table class="grid"><tr>
    <td style="width: 50%;">
        <div class="label">{{ __('Ship to') }}</div>
        @include('documents.partials.address', ['address' => $shipping])
    </td>
    <td style="width: 50%;">
        <div class="label">{{ __('Order') }}</div>
        <div>{{ $order->number }}</div>
        @if ($order->shipping_method_name)
            <div class="label" style="margin-top: 3mm;">{{ __('Shipping method') }}</div>
            <div>{{ $order->shipping_method_name }}</div>
        @endif
    </td>
</tr></table>

<table class="lines">
    <thead><tr>
        <th style="width: 8mm;"></th>
        <th>{{ __('Item') }}</th>
        <th>{{ __('SKU') }}</th>
        <th class="num">{{ __('Qty') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($order->items as $item)
        <tr>
            <td>☐</td>
            <td>{{ $item->product_name }}@if ($item->variant_name)<div class="muted">{{ $item->variant_name }}</div>@endif</td>
            <td>{{ $item->sku }}</td>
            <td class="num"><strong>{{ $item->quantity }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

@if ($order->customer_note)
    <div style="margin-top: 6mm;">
        <div class="label">{{ __('Customer note') }}</div>
        <div class="pre">{{ $order->customer_note }}</div>
    </div>
@endif
@endsection
