<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\StoreNotifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->customer = User::factory()->create();
    }

    private function notify(User $user, string $number): void
    {
        app(StoreNotifier::class)->toCustomer($user, 'customer.order_shipped', ['number' => $number], "/account/orders/{$number}", "shipped:{$number}");
    }

    public function test_customer_sees_own_notifications()
    {
        $this->notify($this->customer, 'EM-1');
        $this->notify($this->customer, 'EM-2');
        $this->notify(User::factory()->create(), 'EM-9');

        $this->actingAs($this->customer)->get(route('account.notifications'))->assertInertia(fn (Assert $page) => $page
            ->component('storefront/account/notifications')
            ->where('notificationsUnread', 2)
            ->has('notifications.data', 2)
            ->where('notifications.data.0.kind', 'customer.order_shipped')
            ->where('notifications.data', fn ($items) => collect($items)->pluck('params.number')->sort()->values()->all() === ['EM-1', 'EM-2']));

        $feed = $this->getJson(route('account.notifications.feed'))->assertOk()->json();
        $this->assertSame(2, $feed['unread']);
        $this->assertCount(2, $feed['items']);
    }

    public function test_open_marks_read()
    {
        $this->notify($this->customer, 'EM-1');
        $notification = $this->customer->notifications()->firstOrFail();

        $this->actingAs($this->customer)->post(route('account.notifications.open', $notification->id))->assertRedirect('/account/orders/EM-1');
        $this->assertNotNull($notification->fresh()->read_at);

        $other = User::factory()->create();
        $this->notify($other, 'EM-5');
        $theirs = $other->notifications()->firstOrFail();
        $this->post(route('account.notifications.open', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->notify($this->customer, 'EM-2');
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $this->customer->unreadNotifications()->count());
    }

    public function test_guest_has_no_bell_prop()
    {
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('notificationsUnread', 0));
        $this->get(route('account.notifications'))->assertRedirect(route('login'));
        $this->getJson(route('account.notifications.feed'))->assertUnauthorized();
    }

    public function test_account_shows_only_customer_notifications()
    {
        $owner = $this->owner();
        app(StoreNotifier::class)->toStaff('customer.new', ['name' => 'Mia', 'email' => 'mia@example.test'], '/admin', 'customer.new:1');
        $this->notify($owner, 'EM-3');
        $staffOnly = $owner->notifications()->where('data->kind', 'customer.new')->firstOrFail();

        $this->actingAs($owner)->get(route('account.notifications'))->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.kind', 'customer.order_shipped'));

        $feed = $this->getJson(route('account.notifications.feed'))->assertOk()->json();
        $this->assertSame(['customer.order_shipped'], array_column($feed['items'], 'kind'));
        $this->assertSame(1, $feed['unread']);

        $this->post(route('account.notifications.open', $staffOnly->id))->assertNotFound();
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertNull($staffOnly->fresh()->read_at);
    }
}
