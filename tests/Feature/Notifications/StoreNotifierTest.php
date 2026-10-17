<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\StoreNotifier;
use App\Models\User;
use App\Notifications\StoreNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class StoreNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    private function notifier(): StoreNotifier
    {
        return app(StoreNotifier::class);
    }

    private function send(string $dedupe = 'order.placed:1'): int
    {
        return $this->notifier()->toStaff('order.placed', ['number' => 'EM-1001', 'total' => '$50.00', 'customer' => 'Ana Cruz'], '/admin/orders/EM-1001', $dedupe);
    }

    public function test_only_staff_with_permission_receive()
    {
        $orders = $this->userWithRole('order-manager');
        $catalog = $this->userWithRole('catalog-manager');
        $customer = User::factory()->create();

        $this->send();

        $this->assertSame(1, $orders->notifications()->count());
        $this->assertSame(0, $catalog->notifications()->count());
        $this->assertSame(0, $customer->notifications()->count());

        $data = $orders->notifications()->firstOrFail()->data;
        $this->assertSame('order.placed', $data['kind']);
        $this->assertSame('/admin/orders/EM-1001', $data['url']);
        $this->assertSame('New order EM-1001', $data['title']);
        $this->assertStringContainsString('Ana Cruz', $data['body']);
    }

    public function test_owner_always_receives()
    {
        $owner = $this->owner();

        $this->send();
        $this->notifier()->toStaff('stock.low', ['sku' => 'MUG-1', 'on_hand' => 2, 'product' => 'Mug'], '/admin/products/1/edit', 'stock.low:1:2');

        $this->assertSame(2, $owner->notifications()->count());
    }

    public function test_actor_is_not_notified()
    {
        $owner = $this->owner();
        $other = $this->userWithRole('order-manager');
        $this->actingAs($owner);

        $this->send();

        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_preferences_turn_bell_and_email_on_or_off()
    {
        Notification::fake();
        $bellOnly = $this->userWithRole('order-manager');
        $emailToo = $this->userWithRole('order-manager');
        $emailToo->forceFill(['notification_preferences' => ['order.placed' => ['bell' => true, 'email' => true]]])->save();
        $off = $this->userWithRole('order-manager');
        $off->forceFill(['notification_preferences' => ['order.placed' => ['bell' => false, 'email' => false]]])->save();

        $this->send();

        Notification::assertSentTo($bellOnly, StoreNotification::class, fn ($n, array $channels) => $channels === ['database']);
        Notification::assertSentTo($emailToo, StoreNotification::class, fn ($n, array $channels) => $channels === ['database', 'mail']);
        Notification::assertNotSentTo($off, StoreNotification::class);
    }

    public function test_same_event_twice_notifies_once()
    {
        $staff = $this->userWithRole('order-manager');

        $this->send('order.paid:7');
        $this->send('order.paid:7');
        $this->send('order.paid:8');

        $this->assertSame(2, $staff->notifications()->count());
    }

    public function test_external_urls_are_dropped()
    {
        $staff = $this->userWithRole('order-manager');

        $this->notifier()->toStaff('order.placed', ['number' => 'EM-1'], 'https://evil.example/x', 'x:1');
        $this->notifier()->toStaff('order.placed', ['number' => 'EM-2'], '//evil.example/x', 'x:2');

        $this->assertSame([null, null], $staff->notifications()->get()->map(fn ($n) => $n->data['url'])->all());
    }

    public function test_customer_notifications_go_to_that_customer_only()
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($this->notifier()->toCustomer($customer, 'customer.order_shipped', ['number' => 'EM-5', 'carrier' => 'DHL', 'tracking' => '123'], '/account/orders/EM-5', 'shipped:5:1'));
        $this->assertFalse($this->notifier()->toCustomer($customer, 'customer.order_shipped', ['number' => 'EM-5'], '/account/orders/EM-5', 'shipped:5:1'));

        $this->assertSame(1, $customer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame('Your order EM-5 has shipped', $customer->notifications()->firstOrFail()->data['title']);
    }

    public function test_prune_removes_old_read_ones()
    {
        $staff = $this->userWithRole('order-manager');
        $this->send('a');
        $this->send('b');
        $this->send('c');
        [$oldRead, $oldUnread, $recentRead] = $staff->notifications()->orderBy('id')->get()->all();
        DB::table('notifications')->where('id', $oldRead->id)->update(['read_at' => now()->subDays(100), 'created_at' => now()->subDays(100)]);
        DB::table('notifications')->where('id', $oldUnread->id)->update(['created_at' => now()->subDays(100)]);
        DB::table('notifications')->where('id', $recentRead->id)->update(['read_at' => now()]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$oldUnread->id, $recentRead->id], $staff->notifications()->pluck('id')->all());
    }

    public function test_one_failing_email_does_not_stop_other_staff()
    {
        $first = $this->userWithRole('order-manager');
        $first->forceFill(['notification_preferences' => ['order.placed' => ['bell' => true, 'email' => true]]])->save();
        $second = $this->userWithRole('order-manager');
        $this->app->bind(MailChannel::class, fn () => new class extends MailChannel
        {
            public function __construct() {}

            public function send($notifiable, BaseNotification $notification)
            {
                throw new RuntimeException('SMTP down');
            }
        });

        $this->send();

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
    }

    public function test_email_only_staff_get_one_email_per_event()
    {
        Notification::fake();
        $emailOnly = $this->userWithRole('order-manager');
        $emailOnly->forceFill(['notification_preferences' => ['order.placed' => ['bell' => false, 'email' => true]]])->save();

        $this->send('stock.low:5:day');
        $this->send('stock.low:5:day');

        Notification::assertSentToTimes($emailOnly, StoreNotification::class, 1);
    }

    public function test_shopper_text_cannot_add_links_to_staff_email()
    {
        $staff = $this->userWithRole('order-manager');
        $mail = (new StoreNotification('order.placed', ['number' => 'EM-1', 'total' => '$1.00', 'customer' => '[Verify payment](https://evil.example)'], null, 'x', ['mail']))->toMail($staff);

        $html = (string) $mail->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringContainsString('Verify payment', $html);
    }
}
