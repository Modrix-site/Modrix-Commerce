<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BotNotificationService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function notifyAttackSpike(int $blockedCount, string $topIp, string $threatType): void
    {
        $this->sendSlackAlert($blockedCount, $topIp, $threatType);
        $this->sendCustomWebhook($blockedCount, $topIp, $threatType);
        $this->sendEmailAlert($blockedCount, $topIp, $threatType);
    }

    private function sendSlackAlert(int $blockedCount, string $topIp, string $threatType): void
    {
        $webhookUrl = (string) $this->settings->of(self::MODULE, 'slack_webhook_url', '');
        if (empty($webhookUrl)) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'text' => ':rotating_light: *Bot Protection Alert*: Attack spike detected on store!',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Bot Attack Spike Detected*\nImmediate automated mitigation in progress.",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Blocked Threats:*\n{$blockedCount} reqs/5m"],
                            ['type' => 'mrkdwn', 'text' => "*Primary Vector:*\n`{$threatType}`"],
                            ['type' => 'mrkdwn', 'text' => "*Top Attacking IP:*\n`{$topIp}`"],
                            ['type' => 'mrkdwn', 'text' => "*Status:*\nThreat Mitigated :shield:"],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed sending Slack bot alert: '.$e->getMessage());
        }
    }

    private function sendCustomWebhook(int $blockedCount, string $topIp, string $threatType): void
    {
        $webhookUrl = (string) $this->settings->of(self::MODULE, 'custom_webhook_url', '');
        if (empty($webhookUrl)) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'event' => 'bot_attack_spike',
                'blocked_count' => $blockedCount,
                'top_ip' => $topIp,
                'threat_type' => $threatType,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed sending custom webhook bot alert: '.$e->getMessage());
        }
    }

    private function sendEmailAlert(int $blockedCount, string $topIp, string $threatType): void
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'email_alerts_enabled', true);
        $recipientsStr = (string) $this->settings->of(self::MODULE, 'alert_emails', '');

        if (! $enabled || empty($recipientsStr)) {
            return;
        }

        $recipients = array_filter(array_map('trim', explode(',', $recipientsStr)));
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::raw(
                "Security Alert: Bot Attack Spike Detected\n\n".
                "Blocked Requests (last 5 min): {$blockedCount}\n".
                "Top Attacking IP: {$topIp}\n".
                "Primary Threat Vector: {$threatType}\n".
                'Timestamp: '.now()->toDateTimeString()."\n\n".
                'Visit your Admin Security Center to review active incidents.',
                function ($message) use ($recipients) {
                    $message->to($recipients)
                        ->subject('[Security Alert] Bot Attack Spike Mitigated');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Failed sending bot alert email: '.$e->getMessage());
        }
    }
}
