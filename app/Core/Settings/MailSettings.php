<?php

namespace App\Core\Settings;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Applies the SMTP settings from Settings → General to Laravel's mail
 * configuration. With "Use server default" the .env values stay in charge.
 * Called when the app boots and before each queued job (queue workers live
 * long, so a change made in the admin must reach them too).
 */
final class MailSettings
{
    /** The .env values, kept so switching back to "server default" restores them. */
    private ?array $original = null;

    public function __construct(private readonly GeneralSettings $general) {}

    public function apply(): void
    {
        $this->original ??= [
            'mail.default' => config('mail.default'),
            'mail.mailers.smtp' => config('mail.mailers.smtp'),
            'mail.from' => config('mail.from'),
            'mail.reply_to' => config('mail.reply_to'),
        ];

        $settings = $this->general->mail();
        $password = $this->general->mailPassword();

        if ($settings['driver'] !== 'smtp' || $settings['host'] === '' || $password === false) {
            config($this->original);
        } else {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => [
                    ...(array) $this->original['mail.mailers.smtp'],
                    'transport' => 'smtp',
                    'url' => null,
                    'scheme' => $settings['encryption'] === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $settings['host'],
                    'port' => $settings['port'],
                    'username' => $settings['username'] !== '' ? $settings['username'] : null,
                    'password' => $password,
                    // "none" disables opportunistic STARTTLS.
                    'auto_tls' => $settings['encryption'] !== 'none',
                ],
                'mail.from' => [
                    'address' => $settings['from_address'] !== '' ? $settings['from_address'] : ($this->original['mail.from']['address'] ?? null),
                    'name' => $settings['from_name'] !== '' ? $settings['from_name'] : ($this->original['mail.from']['name'] ?? null),
                ],
                'mail.reply_to' => $settings['reply_to'] !== '' ? ['address' => $settings['reply_to'], 'name' => null] : $this->original['mail.reply_to'],
            ]);
        }

        // Rebuild mailers with the new configuration on their next use.
        if (app()->resolved('mail.manager')) {
            Mail::purge('smtp');
            Mail::purge((string) config('mail.default'));
        }
    }

    /** Sends a short test message now (not queued) with the current configuration. */
    public function sendTest(string $to): void
    {
        $store = (string) config('app.name');
        Mail::raw("This is a test email from {$store}. If you can read it, your email settings work.", function (Message $message) use ($to, $store) {
            $message->to($to)->subject("Test email from {$store}");
        });
    }

    /** The mail server's error without the saved credentials. */
    public function describeFailure(Throwable $e): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
        $secrets = array_filter([(string) $this->general->mailPassword(), $this->general->mail()['username']], fn ($v) => $v !== '');
        foreach ($secrets as $secret) {
            $message = str_replace($secret, '•••', $message);
        }

        return mb_strimwidth($message !== '' ? $message : $e::class, 0, 300, '…');
    }
}
