<?php

namespace EcoModular\Newsletter\Mail;

use App\Core\Settings\MailSettings;
use EcoModular\Newsletter\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $campaignSubject,
        public readonly string $contentHtml,
        public readonly ?string $previewText,
        public readonly ?Subscriber $subscriber,
        public readonly string $storeName,
        public readonly string $unsubscribeUrl,
    ) {
        // Ensure core SMTP settings are active
        app(MailSettings::class)->apply();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaignSubject,
        );
    }

    public function content(): Content
    {
        $email = $this->subscriber?->email ?? 'subscriber@example.com';
        $name = $this->subscriber?->name ?? 'Valued Customer';

        $body = str_replace(
            ['{{email}}', '{{name}}', '{{store_name}}', '{{unsubscribe_url}}'],
            [e($email), e($name), e($this->storeName), e($this->unsubscribeUrl)],
            $this->contentHtml
        );

        $html = $this->renderEmailTemplate($body);

        return new Content(
            htmlString: $html,
        );
    }

    private function renderEmailTemplate(string $body): string
    {
        $preheader = $this->previewText ? '<span style="display:none;font-size:0px;line-height:0px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">'.e($this->previewText).'</span>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->campaignSubject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f6f7f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f6f7f8; padding: 40px 0; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); }
        .header { padding: 32px 40px 24px; text-align: center; border-bottom: 1px solid #f3f4f6; }
        .header-brand { font-size: 20px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #111827; text-decoration: none; }
        .content { padding: 36px 40px; color: #374151; font-size: 15px; line-height: 1.65; }
        .content h1, .content h2, .content h3 { color: #111827; margin-top: 0; font-weight: 600; }
        .content a { color: #111827; text-decoration: underline; font-weight: 500; }
        .content .btn { display: inline-block; background-color: #111827; color: #ffffff !important; padding: 12px 28px; text-decoration: none !important; border-radius: 4px; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin: 16px 0; }
        .footer { background-color: #f9fafb; padding: 24px 40px; text-align: center; font-size: 12px; line-height: 1.5; color: #6b7280; border-top: 1px solid #f3f4f6; }
        .footer a { color: #6b7280; text-decoration: underline; }
    </style>
</head>
<body>
    {$preheader}
    <table class="wrapper" role="presentation" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table class="main" role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="header">
                            <span class="header-brand">{$this->storeName}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="content">
                            {$body}
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <p style="margin: 0 0 8px 0;">You received this email because you subscribed to updates from <strong>{$this->storeName}</strong>.</p>
                            <p style="margin: 0;"><a href="{$this->unsubscribeUrl}">Unsubscribe at any time</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}
