<?php

namespace App\Http\Controllers\Storefront;

use App\Domains\Notifications\NotificationKinds;
use App\Domains\Notifications\NotificationPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account → Notifications: the customer's own order updates. No polling on
 * the storefront; the bell loads the latest when it is opened. Staff
 * notifications (a staff member who also shops) stay in the admin.
 */
class AccountNotificationController extends Controller
{
    /** Items in the bell's dropdown. */
    public const FEED_LIMIT = 10;

    public function __construct(
        private readonly NotificationPresenter $presenter,
        private readonly NotificationKinds $kinds,
    ) {}

    public function index(Request $request): Response
    {
        $notifications = $this->mine($request)
            ->paginate(20)
            ->through(fn (DatabaseNotification $n) => $this->presenter->present($n));

        return Inertia::render('storefront/account/notifications', ['notifications' => $notifications]);
    }

    public function feed(Request $request): JsonResponse
    {
        $items = $this->mine($request)->limit(self::FEED_LIMIT + 1)->get();

        return response()->json([
            'items' => $items->take(self::FEED_LIMIT)->map(fn (DatabaseNotification $n) => $this->presenter->present($n))->values(),
            'more' => $items->count() > self::FEED_LIMIT,
            'unread' => $this->mine($request)->whereNull('read_at')->count(),
            'cursor' => now()->toIso8601String(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $this->mine($request)->whereKey($id)->first();
        abort_if($notification === null, 404);
        $notification->markAsRead();
        $url = $this->presenter->present($notification)['url'];

        return $url === null ? redirect()->route('account.notifications') : redirect($url);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->mine($request)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /**
     * The signed-in person's customer notifications only.
     *
     * @return MorphMany<DatabaseNotification, User>
     */
    private function mine(Request $request): MorphMany
    {
        // By audience, not name: "customer.new" is a staff kind.
        $customerKinds = array_keys(array_filter(
            $this->kinds->all(),
            fn (array $definition) => $definition['audience'] === NotificationKinds::CUSTOMER,
        ));

        return $this->user($request)->notifications()->whereIn('data->kind', $customerKinds);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
