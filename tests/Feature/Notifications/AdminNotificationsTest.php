<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\StoreNotifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->staff = $this->userWithRole('order-manager');
    }

    private function notify(int $count = 1, int $start = 1): void
    {
        for ($i = $start; $i < $start + $count; $i++) {
            app(StoreNotifier::class)->toStaff('order.placed', ['number' => "EM-{$i}", 'total' => '$1.00', 'customer' => 'Ana'], "/admin/orders/EM-{$i}", "order.placed:{$i}");
        }
    }

    public function test_bell_count_and_page()
    {
        $this->notify(3);
        $this->staff->notifications()->first()->markAsRead();

        $this->actingAs($this->staff)->get(route('admin.notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/index')
            ->where('notificationsUnread', 2)
            ->has('notifications.data', 3)
            ->where('notifications.data.0.title', fn ($t) => str_starts_with($t, 'New order EM-')));

        $this->get(route('admin.notifications.index', ['filter' => 'unread']))->assertInertia(fn (Assert $page) => $page->has('notifications.data', 2));
    }

    public function test_feed_returns_newer_items_capped()
    {
        $this->notify(2);
        $this->actingAs($this->staff);
        $first = $this->getJson(route('admin.notifications.feed'))->assertOk()->json();
        $this->assertSame(2, $first['unread']);
        $this->assertCount(2, $first['items']);

        $this->travel(1)->seconds();
        // Created by the system (not this staff member, who would be the actor).
        auth()->logout();
        $this->notify(25, 100);
        $next = $this->actingAs($this->staff)->getJson(route('admin.notifications.feed', ['after' => $first['cursor']]))->assertOk()->json();
        $this->assertSame(27, $next['unread']);
        $this->assertCount(20, $next['items']);
        $this->assertTrue($next['more']);
    }

    public function test_open_marks_read_and_redirects()
    {
        $this->notify();
        $notification = $this->staff->notifications()->firstOrFail();

        $this->actingAs($this->staff)->post(route('admin.notifications.open', $notification->id))->assertRedirect('/admin/orders/EM-1');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_and_delete()
    {
        $this->notify(3);
        $this->actingAs($this->staff)->post(route('admin.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $this->staff->unreadNotifications()->count());

        $id = $this->staff->notifications()->firstOrFail()->id;
        $this->delete(route('admin.notifications.destroy', $id))->assertRedirect();
        $this->assertSame(2, $this->staff->notifications()->count());
    }

    public function test_cannot_touch_someone_elses_notification()
    {
        $this->notify();
        $theirs = $this->staff->notifications()->firstOrFail();
        $other = $this->userWithRole('order-manager');

        $this->actingAs($other)->post(route('admin.notifications.open', $theirs->id))->assertNotFound();
        $this->delete(route('admin.notifications.destroy', $theirs->id))->assertNotFound();
        $this->assertNotNull(DatabaseNotification::query()->find($theirs->id));
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_settings_list_only_allowed_kinds_and_save()
    {
        // Support agents see orders and customers but not stock.
        $this->staff = $this->userWithRole('support-agent');
        $this->actingAs($this->staff)->get(route('admin.notifications.settings'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/settings')
            ->where('kinds', fn ($kinds) => collect($kinds)->pluck('kind')->contains('order.placed')
                && ! collect($kinds)->pluck('kind')->contains('stock.low')
                && ! collect($kinds)->pluck('kind')->contains('customer.order_shipped')));

        $this->put(route('admin.notifications.settings.update'), ['preferences' => [
            'order.placed' => ['bell' => false, 'email' => true],
            'stock.low' => ['bell' => true, 'email' => true],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['order.placed' => ['bell' => false, 'email' => true]], $this->staff->fresh()->notification_preferences);
    }

    public function test_customers_cannot_open_admin_notifications()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.notifications.index'))->assertForbidden();
    }
}
