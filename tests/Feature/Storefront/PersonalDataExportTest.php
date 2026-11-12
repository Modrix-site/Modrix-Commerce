<?php

namespace Tests\Feature\Storefront;

use App\Domains\Customers\PersonalDataExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * Phase 8.6: customers download the personal data the store holds (GDPR access).
 */
class PersonalDataExportTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function orderFor(User $user, string $email): void
    {
        $this->actingAs($user);
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $email]))->assertRedirect();
    }

    public function test_the_export_contains_the_customers_data_and_nobody_elses()
    {
        $ana = User::factory()->create(['name' => 'Ana Cruz', 'email' => 'ana@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);
        $this->orderFor($ana, 'ana@example.com');
        $this->orderFor($bob, 'bob@example.com');

        app(PersonalDataExport::class)->contribute('sample-module', fn (User $u) => ['hello' => $u->email]);
        app(PersonalDataExport::class)->contribute('broken-module', fn () => throw new RuntimeException('boom'));

        $response = $this->actingAs($ana)->get(route('account.data-export'))
            ->assertOk()
            ->assertHeader('Content-Disposition')
            ->assertJsonPath('account.email', 'ana@example.com')
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.email', 'ana@example.com')
            ->assertJsonPath('orders.0.addresses.0.city', 'Springfield')
            ->assertJsonPath('extensions.sample-module.hello', 'ana@example.com')
            ->assertJsonPath('extensions.broken-module.error', 'This data could not be collected; contact the store.');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('bob@example.com', $body);
        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('two_factor_secret', $body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.data_exported']);
    }

    public function test_the_export_requires_sign_in()
    {
        $this->get(route('account.data-export'))->assertRedirect(route('login'));
    }
}
