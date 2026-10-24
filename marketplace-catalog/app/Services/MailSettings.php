<?php

namespace Marketplace\Services;

use Illuminate\Database\QueryException;
use Marketplace\Models\Setting;

/**
 * Applies the SMTP settings saved in the admin to Laravel's mail config,
 * so a host needs no mail values in .env. Runs when the mailer is first used.
 */
class MailSettings
{
    public function apply(): void
    {
        try {
            $host = (string) Setting::get('smtp_host', '');
        } catch (QueryException) {
            return; // Not installed yet.
        }

        if ($host !== '') {
            $encryption = (string) Setting::get('smtp_encryption', 'tls');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) Setting::get('smtp_port', 587),
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
                'mail.mailers.smtp.username' => Setting::get('smtp_username') ?: null,
                'mail.mailers.smtp.password' => Setting::get('smtp_password') ?: null,
            ]);
        }

        if ($from = Setting::get('mail_from')) {
            config(['mail.from.address' => $from, 'mail.from.name' => Setting::get('mail_from_name') ?: config('app.name')]);
        }
    }
}
