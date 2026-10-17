<?php

namespace App\Notifications;

use App\Domains\Notifications\NotificationKinds;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A store notification (staff or customer bell, optionally email). The
 * stored data keeps the kind and params, so pages can render it again; the
 * English title and body are stored as well. The bell is written at once;
 * email goes through the queue so checkout never waits on a mail server.
 */
class StoreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $params
     * @param  list<string>  $channels  database and/or mail
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $params,
        public readonly ?string $url,
        public readonly string $dedupe,
        public readonly array $channels,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $kinds = app(NotificationKinds::class);

        return [
            'kind' => $this->kind,
            'params' => $this->params,
            'url' => $this->url,
            'dedupe' => $this->dedupe,
            'icon' => $kinds->get($this->kind)['icon'] ?? 'bell',
            ...$kinds->render($this->kind, $this->params),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Values can come from shoppers (a name): no Markdown links or formatting.
        $params = array_map(fn ($value) => is_string($value) ? self::plainMarkdown($value) : $value, $this->params);
        $text = app(NotificationKinds::class)->render($this->kind, $params);
        $mail = (new MailMessage)->subject($text['title'])->line($text['body']);

        return $this->url === null ? $mail : $mail->action('Open', url($this->url));
    }

    /** Escapes Markdown punctuation so text shows as written. */
    private static function plainMarkdown(string $value): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!<>|~])/', '\\\\$1', $value);
    }
}
