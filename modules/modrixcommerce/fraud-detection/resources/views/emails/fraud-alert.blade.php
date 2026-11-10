<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fraud Alert</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; padding: 32px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); overflow: hidden;">
        <div style="background: #dc2626; padding: 24px; text-align: center;">
            <h1 style="color: #fff; margin: 0; font-size: 22px;">⚠️ High-Risk Order Detected</h1>
        </div>
        <div style="padding: 24px;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Order</td>
                    <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ $fraudScore->order?->number ?? '#' . $fraudScore->order_id }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Risk Score</td>
                    <td style="padding: 8px 0; font-weight: 600; text-align: right; color: #dc2626;">{{ $fraudScore->total_score }}/100</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Customer Email</td>
                    <td style="padding: 8px 0; text-align: right;">{{ $fraudScore->customer_email }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-size: 14px;">IP Address</td>
                    <td style="padding: 8px 0; text-align: right;">{{ $fraudScore->ip_address ?? 'Unknown' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Order Total</td>
                    <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ number_format($fraudScore->order_total, 2) }} {{ $fraudScore->currency }}</td>
                </tr>
            </table>

            <h3 style="color: #1e293b; margin: 20px 0 12px; font-size: 16px;">Risk Indicators</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach($fraudScore->indicators as $indicator)
                <li style="padding: 10px 12px; background: #fef2f2; border-left: 3px solid #dc2626; margin-bottom: 8px; border-radius: 4px;">
                    <strong style="color: #991b1b;">{{ $indicator['label'] }}</strong>
                    <span style="color: #dc2626; font-size: 13px;">(+{{ $indicator['score'] }} pts)</span>
                    <br><span style="color: #64748b; font-size: 13px;">{{ $indicator['details'] }}</span>
                </li>
                @endforeach
            </ul>

            <p style="margin-top: 24px; color: #64748b; font-size: 13px;">
                This order has been flagged for manual review. Please log in to the admin dashboard to take action.
            </p>
        </div>
    </div>
</body>
</html>
