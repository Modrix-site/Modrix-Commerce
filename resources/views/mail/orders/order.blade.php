<x-mail::message>
# {{ $heading }}

@switch($kind)
@case('placed')
We have received your order **{{ $order->number }}**.
@if ($paymentInstructions)

**How to pay:** {{ $paymentInstructions }}
@endif
@break
@case('updated')
There is an update on your order **{{ $order->number }}**:

{{ $note }}
@break
@case('confirmed')
Your order **{{ $order->number }}** has been confirmed. We will let you know when it ships.
@break
@case('payment_failed')
The payment for order **{{ $order->number }}** did not go through, and you have not been charged. To complete your order, reply to this email and we will help you pay.
@break
@case('out_for_delivery')
Order **{{ $order->number }}** is out for delivery and should arrive today.
@break
@case('delivered')
Order **{{ $order->number }}** has been delivered. We hope you enjoy it.
@break
@case('delivery_failed')
We could not deliver order **{{ $order->number }}**.@if ($note) {{ $note }}@endif The carrier will try again, or we will contact you.
@break
@case('returned')
We have received the return for order **{{ $order->number }}**. We will let you know about any refund.
@break
@case('paid')
We have received your payment for order **{{ $order->number }}**. We will let you know when it ships.
@break
@case('shipped')
Order **{{ $order->number }}** has been shipped.
@if ($shipment && $shipment->tracking_number)

Tracking number: **{{ $shipment->tracking_number }}**@if ($shipment->carrier) ({{ $shipment->carrier }})@endif
@endif
@break
@case('cancelled')
Order **{{ $order->number }}** has been cancelled. If you have questions, reply to this email.
@break
@case('refunded')
A refund has been issued for order **{{ $order->number }}**. Refunded so far: {{ $order->money('refunded_total')->format() }}.
@break
@case('staff_new_order')
A new order **{{ $order->number }}** was placed by {{ $order->email }} using {{ $order->payment_method_name }}.
@break
@endswitch

@php
    // Digital products: links are issued here too, so the payment email has
    // them whichever OrderPaid listener runs first.
    $downloads = [];
    if (in_array($kind, ['paid', 'placed'], true)) {
        $grants = app(\App\Domains\Downloads\DownloadGrants::class);
        $grants->issueFor($order);
        $downloads = array_values(array_filter($grants->linksFor($order), fn (array $d) => $d['url'] !== null));
    }
@endphp
@if ($downloads !== [])
**Your downloads**

@foreach ($downloads as $download)
- [{{ $download['file'] }}]({{ $download['url'] }}) ({{ $download['product'] }})@if ($download['max_downloads']) · {{ $download['max_downloads'] }} downloads @endif @if ($download['expires_at']) · until {{ \Illuminate\Support\Carbon::parse($download['expires_at'])->toFormattedDateString() }}@endif

@endforeach

@endif
<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->quantity }} | {{ $item->money('subtotal', $order->currency)->format() }} |
@endforeach
</x-mail::table>

| | |
|:--|--:|
| Subtotal | {{ $order->money('subtotal')->format() }} |
@if ($order->money('discount_total')->isPositive())
| Discount | −{{ $order->money('discount_total')->format() }} |
@endif
| Shipping | {{ $order->money('shipping_total')->format() }} |
| Tax{{ $order->prices_include_tax ? ' (included)' : '' }} | {{ $order->money('tax_total')->format() }} |
@if ($order->money('payment_fee_total')->isPositive())
| Cash on delivery fee (collected by the courier) | {{ $order->money('payment_fee_total')->format() }} |
@endif
| **Total** | **{{ $order->money('grand_total')->format() }}** |

@if ($order->shippingAddress)
**Shipping to:** {{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}, {{ $order->shippingAddress->line1 }}, {{ $order->shippingAddress->city }}, {{ \App\Core\Settings\Countries::name($order->shippingAddress->country_code) }}
@endif

Thanks,<br>
{{ $storeName }}
</x-mail::message>
