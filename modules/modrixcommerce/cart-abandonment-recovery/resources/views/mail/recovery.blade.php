<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            -webkit-text-size-adjust: 100%;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .header {
            background-color: #111827;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .headline {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-top: 0;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 15px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 24px;
        }
        .coupon-box {
            background-color: #fef3c7;
            border: 2px dashed #f59e0b;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .coupon-title {
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 700;
            color: #92400e;
            letter-spacing: 0.05em;
            margin: 0 0 6px 0;
        }
        .coupon-code {
            font-size: 22px;
            font-weight: 800;
            color: #b45309;
            letter-spacing: 0.1em;
            display: inline-block;
            background-color: #ffffff;
            padding: 4px 14px;
            border-radius: 6px;
            border: 1px solid #fde68a;
        }
        .coupon-help {
            font-size: 13px;
            color: #78350f;
            margin: 8px 0 0 0;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .cart-table th {
            text-align: left;
            padding: 10px 12px;
            background-color: #f9fafb;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        .cart-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            vertical-align: middle;
        }
        .item-info {
            display: flex;
            align-items: center;
        }
        .item-image {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 12px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
        }
        .item-name {
            font-weight: 600;
            color: #111827;
            margin: 0 0 2px 0;
        }
        .item-sku {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }
        .cart-total-row td {
            font-weight: 700;
            font-size: 16px;
            color: #111827;
            background-color: #f9fafb;
            border-bottom: none;
        }
        .cta-container {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .cta-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 6px;
            text-align: center;
            letter-spacing: 0.025em;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);
        }
        .guarantees {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }
        .footer a {
            color: #6b7280;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $storeName }}</h1>
        </div>
        <div class="content">
            <h2 class="headline">
                @if($step === 1)
                    Did you leave something behind?
                @elseif($step === 2)
                    Your cart items are waiting for you!
                @else
                    Special offer: Complete your order today!
                @endif
            </h2>

            <p class="greeting">
                Hello {{ $customerName ? $customerName : 'there' }},
            </p>

            <p class="intro-text">
                @if($step === 1)
                    We noticed you left some great items in your shopping cart. We saved them for you so you can easily pick up where you left off.
                @elseif($step === 2)
                    We're holding your selected items in your cart, but stock is limited! Click below to review your items and finish your purchase in just a moment.
                @else
                    We don't want you to miss out on the products you liked. Return to your cart now and complete your order.
                @endif
            </p>

            @if(!empty($discountCode))
                <div class="coupon-box">
                    <div class="coupon-title">Special Discount Just For You</div>
                    <div class="coupon-code">{{ $discountCode }}</div>
                    <p class="coupon-help">Use this code at checkout or click below to have it applied automatically!</p>
                </div>
            @endif

            <table class="cart-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Item</th>
                        <th style="text-align: center; width: 15%;">Qty</th>
                        <th style="text-align: right; width: 25%;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <div class="item-info">
                                    @if(!empty($item['image_url']))
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['product_name'] ?? 'Product' }}" class="item-image" />
                                    @endif
                                    <div>
                                        <div class="item-name">{{ $item['product_name'] ?? 'Product' }}</div>
                                        @if(!empty($item['variant_name']))
                                            <p class="item-sku">{{ $item['variant_name'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 500;">
                                {{ $item['quantity'] ?? 1 }}
                            </td>
                            <td style="text-align: right; font-weight: 600;">
                                {{ $item['formatted_price'] ?? '' }}
                            </td>
                        </tr>
                    @endforeach
                    <tr class="cart-total-row">
                        <td colspan="2" style="text-align: right; padding-right: 12px;">Total:</td>
                        <td style="text-align: right;">{{ $formattedSubtotal }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="cta-container">
                <a href="{{ $recoveryUrl }}" class="cta-button" target="_blank">
                    Return to Cart &amp; Checkout &rarr;
                </a>
            </div>

            <div class="guarantees">
                &check; Fast &amp; Secure Checkout &nbsp;&bull;&nbsp; &check; Real-time Stock Reservation
            </div>
        </div>

        <div class="footer">
            <p>You received this email because you started an order at {{ $storeName }}.</p>
            <p>
                If you no longer wish to receive cart reminder emails, you can
                <a href="{{ $unsubscribeUrl }}" target="_blank">unsubscribe here</a>.
            </p>
        </div>
    </div>

    @if(!empty($trackingPixelUrl))
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" style="display:none;" alt="" />
    @endif
</body>
</html>
