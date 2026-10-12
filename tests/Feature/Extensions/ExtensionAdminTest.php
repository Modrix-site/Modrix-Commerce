<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ExtensionAdminTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->makeHelloModule();
    }

    private function hello(): Extension
    {
        app(ModuleRepository::class)->sync();

        return Extension::query()->where('name', 'acme/hello')->firstOrFail();
    }

    public function test_extensions_page_lists_discovered_modules()
    {
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/extensions/index')
                ->has('extensions', 1)
                ->where('extensions.0.name', 'acme/hello')
                ->where('extensions.0.state', 'discovered'));

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.extensions.index'))->assertForbidden();
    }

    public function test_detail_page_shows_capabilities_permissions_and_log()
    {
        $hello = $this->hello();

        $this->actingAs($this->owner())->get(route('admin.extensions.show', $hello))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/extensions/show')
                ->where('extension.permissions.0.name', 'hello.manage')
                ->has('extension.capabilities', 6)
                ->where('settings', null)
                ->has('events', 1));
    }

    public function test_lifecycle_actions_need_permission_and_a_recent_password_confirmation()
    {
        $hello = $this->hello();

        $this->actingAs($this->owner())->post(route('admin.extensions.install', $hello))->assertRedirect(route('password.confirm'));
        $this->actingAsConfirmed($this->userWithRole('analyst'))->post(route('admin.extensions.install', $hello))->assertForbidden();
        $this->assertSame(ModuleState::Discovered, $hello->fresh()->state);
    }

    public function test_full_lifecycle_through_the_admin()
    {
        $hello = $this->hello();
        $admin = $this->userWithRole('administrator');

        $this->actingAsConfirmed($admin)->post(route('admin.extensions.install', $hello))
            ->assertRedirect(route('admin.extensions.show', $hello));
        $this->assertTrue(Schema::hasTable('acme_hello_notes'));

        // The trust confirmation is required.
        $this->actingAsConfirmed($admin)->post(route('admin.extensions.enable', $hello))->assertSessionHasErrors('trusted');
        $this->assertSame(ModuleState::Installed, $hello->fresh()->state);

        $this->actingAsConfirmed($admin)->post(route('admin.extensions.enable', $hello), ['trusted' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(ModuleState::Enabled, $hello->fresh()->state);

        // The module's navigation item appears for permitted staff.
        $this->actingAs($admin->fresh())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('adminNavigation', fn ($groups) => collect($groups)
                ->flatMap(fn ($g) => $g['items'])->contains('key', 'acme/hello:hello')));

        $this->actingAs($admin)->put(route('admin.extensions.settings', $hello), ['settings' => ['greeting' => 'Hey', 'limit' => '99', 'mode' => 'a']])
            ->assertSessionHasErrors('limit');
        $this->actingAs($admin)->put(route('admin.extensions.settings', $hello), ['settings' => ['greeting' => 'Hey', 'limit' => '3', 'mode' => 'a']])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.extensions.show', $hello))
            ->assertInertia(fn (Assert $page) => $page->where('settings.values.greeting', 'Hey')->where('settings.values.limit', 3));

        $this->actingAs($admin)->post(route('admin.extensions.disable', $hello))->assertSessionHasNoErrors();
        $this->assertSame(ModuleState::Disabled, $hello->fresh()->state);

        $this->actingAsConfirmed($admin)->delete(route('admin.extensions.uninstall', $hello), ['confirmation' => 'hello'])
            ->assertSessionHasErrors('module');
        $this->assertTrue(Schema::hasTable('acme_hello_notes'));

        $this->actingAsConfirmed($admin)->delete(route('admin.extensions.uninstall', $hello), ['confirmation' => 'acme/hello'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(Schema::hasTable('acme_hello_notes'));
        $this->assertSame(ModuleState::Discovered, $hello->fresh()->state);
    }

    public function test_refused_operations_are_reported_and_logged()
    {
        $hello = $this->hello();

        $this->actingAsConfirmed($this->owner())->post(route('admin.extensions.enable', $hello), ['trusted' => '1'])
            ->assertSessionHasErrors('module');

        $this->assertDatabaseHas('extension_events', ['extension' => 'acme/hello', 'action' => 'enabled', 'success' => false]);
    }
}
