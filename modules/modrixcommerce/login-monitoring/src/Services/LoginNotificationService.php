<?php

namespace EcoModular\LoginMonitoring\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginNotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LoginNotificationService
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function notifySuspiciousLogin(LoginEvent $event): void
    {
        $this->sendSlackAlert(
            ":warning: *Suspicious Login Detected*",
            "Account: *{$event->email}*\nIP: `{$event->ip_address}`\nRisk Score: *{$event->risk_score}/100*\nLocation: {$event->city}, {$event->country_code}\nDevice: {$event->browser} on {$event->operating_system}"
        );

        $this->sendEmailAlert(
            "[Security Warning] Suspicious Login Detected for {$event->email}",
            "A high-risk login was detected on your store:\n\n" .
            "Email: {$event->email}\n" .
            "IP Address: {$event->ip_address}\n" .
            "Risk Score: {$event->risk_score}/100\n" .
            "Threat Level: " . strtoupper($event->threat_level) . "\n" .
            "Device: {$event->browser} on {$event->operating_system}\n" .
            "Location: {$event->city}, {$event->country_code}\n" .
            "Time: " . now()->toDateTimeString() . "\n\n" .
            "Please review the incident in your Login Monitoring Security Dashboard."
        );
    }

    public function notifyBruteForceLockout(string $email, string $ip, int $attempts): void
    {
        $this->sendSlackAlert(
            ":lock: *Account Lockout - Brute Force Detected*",
            "Target: *{$email}*\nAttacking IP: `{$ip}`\nConsecutive Failed Attempts: *{$attempts}*\nAction: IP & Account temporarily locked out."
        );

        $this->sendEmailAlert(
            "[Security Alert] Brute Force Lockout for {$email}",
            "Multiple failed login attempts ({$attempts}) have triggered a temporary lockout:\n\n" .
            "Target Email: {$email}\n" .
            "IP Address: {$ip}\n" .
            "Time: " . now()->toDateTimeString()
        );
    }

    private function sendSlackAlert(string $title, string $message): void
    {
        $webhookUrl = (string) $this->settings->of(self::MODULE, 'slack_webhook_url', '');
        if (empty($webhookUrl)) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'text' => $title,
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => ['type' => 'mrkdwn', 'text' => "{$title}\n\n{$message}"],
                    ],
                ],
            ]);

            LoginNotificationLog::create([
                'channel' => 'slack',
                'recipient' => 'Slack Security Channel',
                'event_type' => 'security_alert',
                'subject' => $title,
                'status' => 'sent',
                'payload' => ['message' => $message],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed sending Slack login alert: ' . $e->getMessage());
        }
    }

    private function sendEmailAlert(string $subject, string $body): void
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'email_alerts_enabled', true);
        $recipientsStr = (string) $this->settings->of(self::MODULE, 'alert_emails', '');

        if (!$enabled || empty($recipientsStr)) {
            return;
        }

        $recipients = array_filter(array_map('trim', explode(',', $recipientsStr)));
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::raw($body, function ($msg) use ($recipients, $subject) {
                $msg->to($recipients)->subject($subject);
            });

            LoginNotificationLog::create([
                'channel' => 'email',
                'recipient' => implode(', ', $recipients),
                'event_type' => 'security_alert',
                'subject' => $subject,
                'status' => 'sent',
                'payload' => ['body' => $body],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed sending login alert email: ' . $e->getMessage());
        }
    }
}
