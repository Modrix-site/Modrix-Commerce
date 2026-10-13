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
| **Total** | **{{ $order->money('grand_total')->format() }}** |

@if ($order->shippingAddress)
**Shipping to:** {{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}, {{ $order->shippingAddress->line1 }}, {{ $order->shippingAddress->city }}, {{ \App\Core\Settings\Countries::name($order->shippingAddress->country_code) }}
@endif

Thanks,<br>
{{ $storeName }}
</x-mail::message>
