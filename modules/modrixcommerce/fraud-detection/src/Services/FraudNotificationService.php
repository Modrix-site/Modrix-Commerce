<?php

namespace EcoModular\FraudDetection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\FraudDetection\FraudDetectionServiceProvider as Module;
use EcoModular\FraudDetection\Mail\DailyFraudReportMailable;
use EcoModular\FraudDetection\Mail\FraudAlertMailable;
use EcoModular\FraudDetection\Models\FraudNotification;
use EcoModular\FraudDetection\Models\FraudScore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FraudNotificationService
{
    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    /**
     * Send notifications for a high-risk fraud score.
     */
    public function notifyHighRisk(FraudScore $fraudScore): void
    {
        if ($fraudScore->risk_level !== FraudScore::RISK_HIGH) {
            return;
        }

        $this->sendEmailAlert($fraudScore);
        $this->sendSlackAlert($fraudScore);
        $this->createAdminNotification($fraudScore);
    }

    /**
     * Send email alerts to configured recipients.
     */
    private function sendEmailAlert(FraudScore $fraudScore): void
    {
        if (! (bool) $this->settings->of(Module::MODULE, 'email_alerts_enabled', true)) {
            return;
        }

        $recipients = $this->getAlertRecipients();
        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new FraudAlertMailable($fraudScore));

                FraudNotification::create([
                    'fraud_score_id' => $fraudScore->id,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'status' => 'sent',
                ]);
            } catch (Throwable $e) {
                FraudNotification::create([
                    'fraud_score_id' => $fraudScore->id,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send a Slack webhook notification.
     */
    private function sendSlackAlert(FraudScore $fraudScore): void
    {
        if (! (bool) $this->settings->of(Module::MODULE, 'slack_webhook_enabled', false)) {
            return;
        }

        $webhookUrl = trim((string) $this->settings->of(Module::MODULE, 'slack_webhook_url', ''));
        if ($webhookUrl === '') {
            return;
        }

        try {
            $orderNumber = $fraudScore->order?->number ?? '#'.$fraudScore->order_id;
            $payload = [
                'text' => '🚨 *High-Risk Order Detected*',
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => ['type' => 'plain_text', 'text' => '🚨 High-Risk Order Alert'],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Order:*\n{$orderNumber}"],
                            ['type' => 'mrkdwn', 'text' => "*Risk Score:*\n{$fraudScore->total_score}/100"],
                            ['type' => 'mrkdwn', 'text' => "*Email:*\n{$fraudScore->customer_email}"],
                            ['type' => 'mrkdwn', 'text' => "*IP:*\n{$fraudScore->ip_address}"],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => '*Indicators:* '.collect($fraudScore->indicators)->pluck('label')->implode(', '),
                        ],
                    ],
                ],
            ];

            Http::timeout(10)->post($webhookUrl, $payload);

            FraudNotification::create([
                'fraud_score_id' => $fraudScore->id,
                'channel' => 'slack',
                'recipient' => 'webhook',
                'status' => 'sent',
            ]);
        } catch (Throwable $e) {
            FraudNotification::create([
                'fraud_score_id' => $fraudScore->id,
                'channel' => 'slack',
                'recipient' => 'webhook',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create an admin dashboard notification.
     */
    private function createAdminNotification(FraudScore $fraudScore): void
    {
        FraudNotification::create([
            'fraud_score_id' => $fraudScore->id,
            'channel' => 'admin',
            'recipient' => null,
            'status' => 'sent',
        ]);
    }

    /**
     * Send the daily fraud summary report.
     */
    public function sendDailyReport(): void
    {
        if (! (bool) $this->settings->of(Module::MODULE, 'daily_report_enabled', true)) {
            return;
        }

        $recipients = $this->getAlertRecipients();
        if (empty($recipients)) {
            return;
        }

        $dashboard = app(FraudDashboardService::class);
        $stats = $dashboard->getStats();

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new DailyFraudReportMailable($stats));
            } catch (Throwable) {
                // Fail silently for daily reports
            }
        }
    }

    /**
     * @return string[]
     */
    private function getAlertRecipients(): array
    {
        $raw = trim((string) $this->settings->of(Module::MODULE, 'alert_email_recipients', ''));
        if ($raw === '') {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        );
    }
}
