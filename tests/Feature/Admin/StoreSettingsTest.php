<?php

namespace Tests\Feature\Admin;

use App\Core\Settings\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
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
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Acme Goods',
            'store_email' => 'hello@acme.test',
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'weight_unit' => 'g',
            'dimension_unit' => 'mm',
        ], $overrides);
    }

    public function test_settings_are_persisted_and_audited()
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)
            ->put(route('admin.settings.store.update'), $this->valid())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.store.edit'));

        $settings = app(StoreSettings::class)->toArray();
        $this->assertSame('Acme Goods', $settings['store_name']);
        $this->assertSame('EUR', $settings['default_currency']);
        $this->assertSame('Europe/Paris', $settings['timezone']);
        $this->assertSame('g', $settings['weight_unit']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.store_updated', 'actor_id' => $admin->id]);
    }

    public function test_invalid_values_are_rejected()
    {
        $this->actingAs($this->owner())
            ->put(route('admin.settings.store.update'), $this->valid([
                'default_currency' => 'XXX',
                'timezone' => 'Mars/Olympus',
                'weight_unit' => 'stone',
            ]))
            ->assertSessionHasErrors(['default_currency', 'timezone', 'weight_unit']);
    }

    public function test_staff_without_permission_cannot_change_settings()
    {
        $this->actingAs($this->userWithRole('catalog-manager'))
            ->put(route('admin.settings.store.update'), $this->valid())
            ->assertForbidden();

        $this->assertNotSame('Acme Goods', app(StoreSettings::class)->name());
    }
}
