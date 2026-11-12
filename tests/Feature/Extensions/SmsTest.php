<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use ModrixCommerce\Sms\Contracts\SmsProvider;
use ModrixCommerce\Sms\Models\SmsMessageLog;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\PhoneNumber;
use ModrixCommerce\Sms\Providers\AwsSnsProvider;
use ModrixCommerce\Sms\Providers\CustomHttpProvider;
use ModrixCommerce\Sms\Providers\TwilioProvider;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsProviders;
use ModrixCommerce\Sms\SmsResult;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * New customers give a mobile number and confirm it with a texted code
 * before using their account; confirmed customers get order updates by text
 * through the provider the store chose.
 */
class SmsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    /** @var list<SmsMessage> */
    public static array $outbox = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/sms'), $this->modulesPath.'/modrixcommerce/sms');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/sms');
        app(ModuleManager::class)->enable('modrixcommerce/sms', true);

        self::$outbox = [];
        app(SmsProviders::class)->register(new class implements SmsProvider
        {
            public function code(): string
            {
                return 'fake';
            }

            public function label(): string
            {
                return 'Fake';
            }

            public function fields(): array
            {
                return [];
            }

            public function send(SmsMessage $message, array $config): SmsResult
            {
                SmsTest::$outbox[] = $message;

                return SmsResult::sent('fake-'.count(SmsTest::$outbox));
            }
        });
        app(SettingsRepository::class)->setMany(['sms.provider' => 'fake', 'sms.default_country_code' => '63']);
    }

    public function test_registration_requires_a_mobile_number_and_holds_the_account_until_the_code_is_entered(): void
    {
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->where('registerSlots.0.component', 'modrixcommerce/sms/RegisterPhone')
            ->where('registerSlots.0.props.defaultCountryCode', '63'));

        $this->post(route('register.store'), $this->registration(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post(route('register.store'), $this->registration(['phone' => 'call me']))->assertSessionHasErrors('phone');
        $this->assertGuest();

        $this->post(route('register.store'), $this->registration(['phone' => '0917 123 4567', 'sms_updates' => '1']))->assertRedirect();

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
        $phone = SmsPhone::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('+639171234567', $phone->phone);
        $this->assertNull($phone->verified_at);
        $this->assertTrue($phone->order_updates);

        $this->assertCount(1, self::$outbox);
        $code = self::$outbox[0]->variables['code'];
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertStringContainsString($code, self::$outbox[0]->body);
        // The log never keeps the code.
        $log = SmsMessageLog::query()->where('purpose', 'verification')->firstOrFail();
        $this->assertStringNotContainsString($code, $log->body);
        $this->assertSame('sent', $log->status);

        // Isolate the SMS hold from email verification (covered elsewhere).
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh());
        $this->get(route('home'))->assertOk();
        $this->get(route('account.show'))->assertRedirect(route('modrixcommerce.sms.verify'));
        $this->get(route('modrixcommerce.sms.verify'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('module/modrixcommerce/sms/storefront/verify', false)
            ->where('phone', '+639171234567'));

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post(route('modrixcommerce.sms.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertNull($phone->fresh()->verified_at);

        $this->post(route('modrixcommerce.sms.confirm'), ['code' => $code])->assertRedirect();
        $this->assertNotNull($phone->fresh()->verified_at);

        // No longer held by the SMS gate (email verification may still apply).
        $this->assertNotSame(route('modrixcommerce.sms.verify'), $this->get(route('account.show'))->headers->get('Location'));
    }

    public function test_five_wrong_codes_burn_the_code(): void
    {
        $this->post(route('register.store'), $this->registration(['phone' => '+639171234567']))->assertRedirect();
        $code = self::$outbox[0]->variables['code'];
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('modrixcommerce.sms.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->post(route('modrixcommerce.sms.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(SmsPhone::query()->firstOrFail()->verified_at);
    }

    public function test_resending_is_rate_limited(): void
    {
        $this->post(route('register.store'), $this->registration(['phone' => '+639171234567']))->assertRedirect();
        $this->post(route('modrixcommerce.sms.resend'))->assertSessionHasErrors('code');
        $this->assertCount(1, self::$outbox);

        $this->travel(61)->seconds();
        $this->post(route('modrixcommerce.sms.resend'))->assertSessionHasNoErrors();
        $this->assertCount(2, self::$outbox);
    }

    public function test_a_number_confirmed_on_another_account_cannot_be_reused(): void
    {
        $other = User::factory()->create();
        SmsPhone::query()->create(['user_id' => $other->id, 'phone' => '+639171234567', 'verified_at' => now()]);

        $this->post(route('register.store'), $this->registration(['phone' => '09171234567']))->assertSessionHasErrors('phone');
        $this->assertCount(0, self::$outbox);
    }

    public function test_without_a_working_provider_registration_does_not_ask_for_a_number(): void
    {
        app(SettingsRepository::class)->set('sms.provider', 'twilio'); // no credentials

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('registerSlots', []));
        $this->post(route('register.store'), $this->registration())->assertRedirect();
        $this->assertSame(0, SmsPhone::query()->count());
        $this->get(route('account.show'))->assertRedirect(route('verification.notice'));
    }

    public function test_existing_customers_can_add_a_number_without_being_locked_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modrixcommerce.sms.change'), ['phone' => '+447700900123'])
            ->assertRedirect(route('modrixcommerce.sms.verify'));
        $this->get(route('account.show'))->assertOk();

        $code = self::$outbox[0]->variables['code'];
        $this->post(route('modrixcommerce.sms.confirm'), ['code' => $code])->assertRedirect(route('modrixcommerce.sms.account'));

        $phone = SmsPhone::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('+447700900123', $phone->phone);
        $this->assertNotNull($phone->verified_at);
        $this->assertNull($phone->pending_phone);
    }

    public function test_confirmed_customers_get_order_updates_once_per_event(): void
    {
        $this->enableCheckout();
        $variant = $this->sellable('10.00', 5);
        $buyer = User::factory()->create(['name' => 'Ana']);
        SmsPhone::query()->create(['user_id' => $buyer->id, 'phone' => '+639171234567', 'verified_at' => now(), 'order_updates' => true]);

        $this->actingAs($buyer);
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $buyer->email]))->assertRedirect();
        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $texts = array_values(array_filter(self::$outbox, fn (SmsMessage $m) => $m->purpose === SmsMessage::PURPOSE_ORDER_UPDATE));
        $this->assertCount(1, $texts);
        $this->assertSame('+639171234567', $texts[0]->to);
        $this->assertStringContainsString((string) $order->number, $texts[0]->body);
        $this->assertStringContainsString('Ana', $texts[0]->body);

        event(new OrderPlaced($order->id));
        $this->assertSame(1, SmsMessageLog::query()->where('order_id', $order->id)->where('event', 'order_placed')->count());
        $this->assertCount(1, array_filter(self::$outbox, fn (SmsMessage $m) => $m->purpose === SmsMessage::PURPOSE_ORDER_UPDATE));

        // Turning order texts off stops them.
        $this->put(route('modrixcommerce.sms.preferences'), ['order_updates' => false])->assertRedirect();
        $this->assertFalse(SmsPhone::query()->firstOrFail()->order_updates);
    }

    public function test_admin_saves_provider_credentials_encrypted_and_never_returns_them(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);

        $this->put(route('admin.modrixcommerce.sms.update'), $this->adminPayload([
            'provider' => 'twilio',
            'config' => ['account_sid' => 'AC'.str_repeat('a', 32), 'auth_token' => 'super-secret-token', 'from' => '+15005550006', 'messaging_service_sid' => ''],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('super-secret-token', app(SecretSettings::class)->get('sms.twilio.auth_token'));
        $this->assertNotSame('super-secret-token', app(SettingsRepository::class)->get('secrets.sms.twilio.auth_token'));

        $this->get(route('admin.modrixcommerce.sms.index'))->assertOk()
            ->assertDontSee('super-secret-token')
            ->assertInertia(fn (Assert $page) => $page->where('settings.provider', 'twilio')->where('settings.problems', []));

        // Required template placeholder.
        $this->put(route('admin.modrixcommerce.sms.update'), $this->adminPayload(['verification_template' => 'no code here']))
            ->assertSessionHasErrors('verification_template');
    }

    public function test_customers_cannot_open_the_admin_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.modrixcommerce.sms.index'))->assertForbidden();
    }

    public function test_twilio_and_aws_sns_send_the_right_requests(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            'sns.us-east-1.amazonaws.com/*' => Http::response('<PublishResponse><PublishResult><MessageId>abc-1</MessageId></PublishResult></PublishResponse>'),
        ]);
        $message = new SmsMessage('+15551234567', 'Hello & welcome', SmsMessage::PURPOSE_TEST);

        $twilio = (new TwilioProvider)->send($message, ['account_sid' => 'AC'.str_repeat('b', 32), 'auth_token' => 't', 'from' => '+15005550006', 'messaging_service_sid' => '']);
        $this->assertTrue($twilio->sent);
        $this->assertSame('SM123', $twilio->messageId);

        $sns = (new AwsSnsProvider)->send($message, ['access_key_id' => 'AKIDEXAMPLE', 'secret_access_key' => 'secret', 'region' => 'us-east-1', 'sender_id' => '']);
        $this->assertTrue($sns->sent);
        $this->assertSame('abc-1', $sns->messageId);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.twilio.com') && $r['To'] === '+15551234567' && $r['Body'] === 'Hello & welcome');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'amazonaws.com')
            && str_starts_with($r->header('Authorization')[0] ?? '', 'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/')
            && str_contains($r->body(), 'PhoneNumber=%2B15551234567'));
    }

    public function test_custom_http_gateway_escapes_values_and_refuses_private_hosts(): void
    {
        Http::fake(['sms.example.com/*' => Http::response(['data' => ['id' => 'm-9'], 'ok' => true])]);
        $provider = new CustomHttpProvider;
        $config = [
            'url' => 'https://sms.example.com/send?to={to_digits}', 'method' => 'POST', 'format' => 'json',
            'body' => '{"to": "{to}", "text": "{message}"}', 'headers' => 'Authorization: Bearer {api_key}',
            'api_key' => 'k-1', 'sender' => '', 'success_contains' => '"ok":true', 'message_id_path' => 'data.id',
        ];

        $result = $provider->send(new SmsMessage('+15551234567', 'Say "hi"', SmsMessage::PURPOSE_TEST), $config);
        $this->assertTrue($result->sent);
        $this->assertSame('m-9', $result->messageId);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://sms.example.com/send?to=15551234567'
            && $r->header('Authorization')[0] === 'Bearer k-1'
            && json_decode($r->body(), true) === ['to' => '+15551234567', 'text' => 'Say "hi"']);

        foreach (['http://sms.example.com', 'https://127.0.0.1/send', 'https://10.0.0.5/', 'https://localhost/x'] as $url) {
            $this->assertFalse($provider->send(new SmsMessage('+15551234567', 'x', SmsMessage::PURPOSE_TEST), ['url' => $url] + $config)->sent, $url);
        }
    }

    public function test_phone_numbers_are_normalised_to_e164(): void
    {
        $this->assertSame('+639171234567', PhoneNumber::normalize('0917-123-4567', '63'));
        $this->assertSame('+447700900123', PhoneNumber::normalize('0044 7700 900123', '1'));
        $this->assertSame('+15551234567', PhoneNumber::normalize('(555) 123-4567', '1'));
        $this->assertSame('+919876543210', PhoneNumber::normalize('+91 98765 43210', '1'));
        $this->assertNull(PhoneNumber::normalize('12', '1'));
        $this->assertNull(PhoneNumber::normalize('555-CALL', '1'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Ana Cruz',
            'email' => 'ana@example.com',
            'password' => 'a-Strong-passphrase-1',
            'password_confirmation' => 'a-Strong-passphrase-1',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adminPayload(array $overrides = []): array
    {
        $events = [];
        foreach (['order_placed', 'order_paid', 'order_shipped', 'order_delivered', 'order_cancelled', 'order_refunded'] as $event) {
            $events[$event] = ['enabled' => true, 'template' => '{store}: order {order_number} update'];
        }

        return [
            'provider' => 'fake',
            'default_country_code' => '+63',
            'require_at_registration' => true,
            'code_minutes' => 10,
            'verification_template' => 'Code {code}',
            'events' => $events,
            'config' => [],
            ...$overrides,
        ];
    }
}
