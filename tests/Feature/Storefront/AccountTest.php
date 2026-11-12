<?php

namespace Tests\Feature\Storefront;

use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    /**
     * @return array<string, string>
     */
    private function address(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'line1' => '1 Rizal Ave',
            'city' => 'Manila',
            'postal_code' => '1000',
            'country_code' => 'PH',
        ], $overrides);
    }

    public function test_registration_creates_a_customer_profile()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'Ana Cruz',
            'email' => 'ana@example.com',
            'password' => 'a-Strong-passphrase-1',
            'password_confirmation' => 'a-Strong-passphrase-1',
        ]);

        $customer = Customer::query()->where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('Ana', $customer->first_name);
        $this->assertNotNull($customer->user_id);
    }

    public function test_address_book_crud_and_defaults()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->address())->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->address(['line1' => '2 Second St', 'is_default_shipping' => true]))->assertSessionHasNoErrors();

        $customer = app(CustomerProfiles::class)->forUser($user);
        [$first, $second] = $customer->addresses()->reorder('id')->get()->all();
        $this->assertFalse($first->is_default_shipping);
        $this->assertTrue($first->is_default_billing);
        $this->assertTrue($second->is_default_shipping);

        $this->actingAs($user)->delete(route('account.addresses.destroy', $second))->assertRedirect();
        $this->assertTrue($first->fresh()?->is_default_shipping);
    }

    public function test_invalid_country_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('account.addresses.store'), $this->address(['country_code' => 'XX']))
            ->assertSessionHasErrors('country_code');
    }

    public function test_users_cannot_touch_other_customers_addresses()
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('account.addresses.store'), $this->address());
        $address = CustomerAddress::query()->firstOrFail();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->put(route('account.addresses.update', $address), $this->address(['city' => 'Hacked']))->assertNotFound();
        $this->actingAs($intruder)->delete(route('account.addresses.destroy', $address))->assertNotFound();

        $this->assertSame('Manila', $address->fresh()?->city);
    }

    public function test_account_requires_login()
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
    }
}
