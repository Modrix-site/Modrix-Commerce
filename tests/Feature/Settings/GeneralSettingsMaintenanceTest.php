<?php

namespace Tests\Feature\Settings;

use App\Core\Settings\GeneralSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralSettingsMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    private function close(array $overrides = []): void
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.maintenance'), $overrides + [
            'enabled' => true, 'headline' => 'Back soon', 'message' => 'New collection arriving.', 'reopens_at' => null, 'allowed_ips' => [],
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_shoppers_get_the_maintenance_page_with_retry_after()
    {
        $this->close(['reopens_at' => now()->addHours(2)->format('Y-m-d H:i')]);

        $response = $this->get('/')->assertStatus(503)->assertSee('Back soon')->assertSee('New collection arriving.');
        $this->assertGreaterThan(3600, (int) $response->headers->get('Retry-After'));
        $this->get('/search')->assertStatus(503);
    }

    public function test_admin_sign_in_webhooks_and_health_keep_working()
    {
        $this->close();

        $this->get(route('login'))->assertOk();
        $this->get('/health')->assertStatus(200);
        $this->post('/webhooks/payments/unknown')->assertStatus(404);
        $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_staff_and_allowed_addresses_still_see_the_store()
    {
        $this->close(['allowed_ips' => ['10.1.2.3']]);

        $this->actingAs($this->owner())->get('/')->assertOk()->assertSee('Maintenance mode is on');
        auth()->logout();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get('/')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->actingAs(User::factory()->create())->get('/')->assertStatus(503);
    }

    public function test_opening_the_store_again()
    {
        $this->close();
        $this->actingAs($this->owner())->put(route('admin.settings.general.maintenance'), ['enabled' => false, 'headline' => 'Back soon', 'message' => '', 'reopens_at' => null, 'allowed_ips' => []]);
        auth()->logout();

        $this->get('/')->assertOk();
        $this->assertFalse(app(GeneralSettings::class)->maintenance()['enabled']);
    }

    public function test_invalid_addresses_and_dates_are_refused()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.maintenance'), [
            'enabled' => true, 'headline' => 'Back soon', 'message' => '', 'reopens_at' => 'tomorrow', 'allowed_ips' => ['not-an-ip'],
        ])->assertSessionHasErrors(['reopens_at', 'allowed_ips.0']);
        $this->assertFalse(app(GeneralSettings::class)->maintenance()['enabled']);
    }
}
