<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Customers verify their email once, right after registering, before the
 * account area opens. Once verified they are not asked again.
 */
class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
        $this->installPlatform();
    }

    public function test_registering_sends_a_verification_email_and_lands_on_the_notice(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Ana Cruz',
            'email' => 'ana@example.com',
            'password' => 'a-Strong-passphrase-1',
            'password_confirmation' => 'a-Strong-passphrase-1',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('account.show'))->assertRedirect(route('verification.notice'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function accountRoutes(): array
    {
        return [
            'overview' => ['account.show'],
            'orders' => ['account.orders'],
            'addresses' => ['account.addresses'],
            'profile' => ['account.profile'],
            'notifications' => ['account.notifications'],
            'password' => ['account.password'],
        ];
    }

    #[DataProvider('accountRoutes')]
    public function test_an_unverified_customer_is_sent_to_the_notice_from_account_pages(string $route): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
    }

    public function test_an_unverified_customer_cannot_change_account_data(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), [
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'line1' => '1 Rizal Ave',
            'city' => 'Manila', 'postal_code' => '1000', 'country_code' => 'PH',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    public function test_a_verified_customer_reaches_the_account_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.show'))->assertOk();
        $this->actingAs($user)->get(route('account.orders'))->assertOk();
    }

    public function test_verifying_once_is_enough_for_later_logins(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'a-Strong-passphrase-1']);

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($link)->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-Strong-passphrase-1',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))->assertRedirect(route('account.show'));
        $this->get(route('account.show'))->assertOk();
    }

    public function test_the_verification_link_works_after_signing_in_from_another_browser(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'a-Strong-passphrase-1']);

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        // Signed out: the link asks them to sign in, then finishes verifying.
        $this->get($link)->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-Strong-passphrase-1',
        ])->assertRedirect($link);

        $this->get($link);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_changing_the_email_asks_for_verification_of_the_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->actingAs($user->fresh())->get(route('account.show'))->assertRedirect(route('verification.notice'));
    }

    public function test_saving_the_profile_without_changing_the_email_keeps_it_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_checkout_is_not_behind_verification(): void
    {
        // Guests, and customers still to verify, can keep shopping.
        $this->assertNotSame(route('verification.notice'), $this->get(route('checkout.show'))->headers->get('Location'));

        $this->actingAs(User::factory()->unverified()->create());
        $this->assertNotSame(route('verification.notice'), $this->get(route('checkout.show'))->headers->get('Location'));
    }
}
