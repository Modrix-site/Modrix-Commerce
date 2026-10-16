<?php

namespace App\Notifications;

use App\Domains\Notifications\NotificationKinds;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A store notification (staff or customer bell, optionally email). The
 * stored data keeps the kind and params, so pages can render it again; the
 * English title and body are stored as well.
 */
class StoreNotification extends Notification
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
        $text = app(NotificationKinds::class)->render($this->kind, $this->params);
        $mail = (new MailMessage)->subject($text['title'])->line($text['body']);

        return $this->url === null ? $mail : $mail->action('Open', url($this->url));
    }
}
