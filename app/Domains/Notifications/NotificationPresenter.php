<?php

namespace App\Domains\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * A stored notification as the bells and pages show it.
 */
final class NotificationPresenter
{
    public function __construct(private readonly NotificationKinds $kinds) {}

    /**
     * @return array{id: string, kind: string, title: string, body: string, icon: string, url: string|null, params: array<string, mixed>, read: bool, created_at: string|null}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;
        $kind = (string) ($data['kind'] ?? '');
        $params = is_array($data['params'] ?? null) ? $data['params'] : [];
        // Text from the current definitions, falling back to what was stored.
        $text = $this->kinds->get($kind) === null
            ? ['title' => (string) ($data['title'] ?? ''), 'body' => (string) ($data['body'] ?? '')]
            : $this->kinds->render($kind, $params);

        return [
            'id' => (string) $notification->id,
            'kind' => $kind,
            ...$text,
            'icon' => (string) ($data['icon'] ?? 'bell'),
            'url' => StoreNotifier::safeUrl(is_string($data['url'] ?? null) ? $data['url'] : null),
            'params' => $params,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
