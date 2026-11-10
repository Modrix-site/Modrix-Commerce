<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Fraud Report</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; padding: 32px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); overflow: hidden;">
        <div style="background: #1e293b; padding: 24px; text-align: center;">
            <h1 style="color: #fff; margin: 0; font-size: 22px;">📊 Daily Fraud Summary</h1>
            <p style="color: #94a3b8; margin: 8px 0 0; font-size: 14px;">{{ now()->toFormattedDateString() }}</p>
        </div>
        <div style="padding: 24px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div style="background: #f1f5f9; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #1e293b;">{{ $stats['total_analyzed'] ?? 0 }}</div>
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Orders Analyzed</div>
                </div>
                <div style="background: #fef2f2; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #dc2626;">{{ $stats['high_risk'] ?? 0 }}</div>
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">High Risk</div>
                </div>
                <div style="background: #fffbeb; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #d97706;">{{ $stats['medium_risk'] ?? 0 }}</div>
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Medium Risk</div>
                </div>
                <div style="background: #f0fdf4; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #16a34a;">{{ $stats['low_risk'] ?? 0 }}</div>
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Low Risk</div>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Pending Review</td>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; font-weight: 600; text-align: right;">{{ $stats['pending_review'] ?? 0 }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Fraud Detected (Rejected)</td>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; font-weight: 600; text-align: right; color: #dc2626;">{{ $stats['fraud_detected'] ?? 0 }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Average Risk Score</td>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; font-weight: 600; text-align: right;">{{ $stats['average_score'] ?? 0 }}/100</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Detection Rate</td>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0; font-weight: 600; text-align: right;">{{ $stats['detection_rate'] ?? 0 }}%</td>
                </tr>
            </table>

            <p style="margin-top: 24px; color: #64748b; font-size: 13px; text-align: center;">
                Visit the Fraud Detection dashboard for detailed analytics and to review flagged orders.
            </p>
        </div>
    </div>
</body>
</html>
