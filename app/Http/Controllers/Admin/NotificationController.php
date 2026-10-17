<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Notifications\NotificationKinds;
use App\Domains\Notifications\NotificationPresenter;
use App\Domains\Notifications\NotificationVisibility;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Notifications: the staff member's own notifications, the bell's
 * live feed, and which kinds they get (bell, email).
 */
class NotificationController extends Controller
{
    /** Most items the live feed returns at once. */
    public const FEED_LIMIT = 20;

    /** Seconds the next feed call looks back, so a row stored while a response was built is not missed. */
    public const FEED_OVERLAP = 5;

    public function __construct(
        private readonly NotificationPresenter $presenter,
        private readonly NotificationVisibility $visibility,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $unreadOnly = $request->query('filter') === 'unread';

        $visible = $this->visibility->query($user);
        $notifications = ($unreadOnly ? $visible->whereNull('read_at') : $visible)
            ->paginate(25)->withQueryString()
            ->through(fn (DatabaseNotification $n) => $this->presenter->present($n));

        return Inertia::render('admin/notifications/index', [
            'notifications' => $notifications,
            'filter' => $unreadOnly ? 'unread' : 'all',
        ]);
    }

    /**
     * New notifications since `after` (an ISO time from the previous call) and
     * the unread count, for the bell's 30-second check.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $after = $request->query('after');
        // Taken before the query and moved back a little; the browser skips ids it already showed.
        $cursor = now()->subSeconds(self::FEED_OVERLAP);
        $query = $this->visibility->query($user);

        if (is_string($after) && $after !== '') {
            try {
                // Same second included: the browser skips ids it already showed.
                $query->where('created_at', '>=', Carbon::parse($after));
            } catch (\Throwable) {
                // An invalid cursor just returns the latest items.
            }
        }

        $items = $query->limit(self::FEED_LIMIT + 1)->get();

        return response()->json([
            'items' => $items->take(self::FEED_LIMIT)->map(fn (DatabaseNotification $n) => $this->presenter->present($n))->values(),
            'more' => $items->count() > self::FEED_LIMIT,
            'unread' => $this->visibility->unreadCount($user),
            'cursor' => $cursor->toIso8601String(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $this->find($request, $id);
        $notification->markAsRead();
        $url = $this->presenter->present($notification)['url'];

        return $url === null ? redirect()->route('admin.notifications.index') : redirect($url);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $this->find($request, $id)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->visibility->query($this->user($request))->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->find($request, $id)->delete();

        return back();
    }

    public function settings(Request $request, NotificationKinds $kinds): Response
    {
        $user = $this->user($request);

        return Inertia::render('admin/notifications/settings', [
            'kinds' => collect($this->allowedKinds($user, $kinds))->map(fn (array $definition, string $kind) => [
                'kind' => $kind,
                'label' => $definition['label'],
                'help' => $definition['help'] ?? null,
                'bell' => $user->wantsNotification($kind, 'bell'),
                'email' => $user->wantsNotification($kind, 'email'),
            ])->values(),
        ]);
    }

    public function updateSettings(Request $request, NotificationKinds $kinds): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['preferences' => ['array']]);
        $preferences = [];
        $errors = [];

        // Only kinds this person may receive are stored.
        foreach (array_keys($this->allowedKinds($user, $kinds)) as $kind) {
            $choice = $data['preferences'][$kind] ?? null;

            if (! is_array($choice)) {
                continue;
            }

            $bell = filter_var($choice['bell'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $email = filter_var($choice['email'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($bell === null || $email === null) {
                $errors['preferences'] = 'Each choice must be on or off.';

                continue;
            }

            $preferences[$kind] = ['bell' => $bell, 'email' => $email];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $user->forceFill(['notification_preferences' => $preferences])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return back();
    }

    /**
     * @return array<string, array{audience: string, permission: string|null, icon: string, label: string, title: string, body: string, help?: string}>
     */
    private function allowedKinds(User $user, NotificationKinds $kinds): array
    {
        return array_filter($kinds->all(), fn (array $definition) => $definition['audience'] === NotificationKinds::STAFF
            && ($user->isOwner() || $user->can((string) $definition['permission'])));
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        $notification = $this->visibility->query($this->user($request))->whereKey($id)->first();
        abort_if($notification === null, 404);

        return $notification;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
