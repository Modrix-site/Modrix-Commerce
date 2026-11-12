<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Deleting a module from Admin → Extensions removes its data, its package
 * files and its record (the step after Uninstall).
 */
class ModuleDeleteTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->makeHelloModule();
        $this->modules = app(ModuleManager::class);
    }

    private function extension(string $name = 'acme/hello'): ?Extension
    {
        app(ModuleRepository::class)->sync();

        return Extension::query()->where('name', $name)->first();
    }

    private function deleteRequest(Extension $extension, ?string $confirmation = null)
    {
        return $this->delete(route('admin.extensions.destroy', $extension), ['confirmation' => $confirmation ?? $extension->name]);
    }

    public function test_deleting_a_disabled_module_removes_its_data_files_and_record()
    {
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);
        $this->modules->disable('acme/hello');
        $this->assertTrue(Schema::hasTable('acme_hello_notes'));
        $hello = $this->extension();

        $this->actingAsConfirmed($this->owner())->from(route('admin.extensions.show', $hello))
            ->deleteRequest($hello)
            ->assertRedirect(route('admin.extensions.index'))
            ->assertSessionHasNoErrors();

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme');
        $this->assertDatabaseMissing('extensions', ['name' => 'acme/hello']);
        $this->assertFalse(Schema::hasTable('acme_hello_notes'));
        $this->assertDatabaseMissing('permissions', ['name' => 'hello.manage']);
        $this->assertDatabaseHas('extension_events', ['extension' => 'acme/hello', 'action' => 'deleted', 'success' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'extension.deleted']);

        // A rescan does not bring it back.
        $this->assertNull($this->extension());
    }

    public function test_a_module_that_was_never_installed_can_be_deleted()
    {
        $hello = $this->extension();
        $this->assertSame(ModuleState::Discovered, $hello->state);

        $this->actingAsConfirmed($this->owner())->deleteRequest($hello)->assertRedirect(route('admin.extensions.index'));

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
        $this->assertDatabaseMissing('extensions', ['name' => 'acme/hello']);
    }

    public function test_an_enabled_module_cannot_be_deleted()
    {
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);
        $hello = $this->extension();

        $this->actingAsConfirmed($this->owner())->deleteRequest($hello)->assertSessionHasErrors('module');

        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
        $this->assertSame(ModuleState::Enabled, $this->extension()->state);
        $this->assertTrue(Schema::hasTable('acme_hello_notes'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'extension.deleted']); // the refusal is logged
    }

    public function test_a_module_required_by_an_installed_module_cannot_be_deleted()
    {
        $this->makeModule('acme/needs-hello', ['requires' => ['acme/hello' => '^1.0']]);
        $this->modules->install('acme/hello');
        $this->modules->install('acme/needs-hello');
        $hello = $this->extension();

        $this->actingAsConfirmed($this->owner())->deleteRequest($hello)
            ->assertSessionHasErrors(['module' => 'Uninstall or delete acme/needs-hello first: they depend on this module.']);

        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
        $this->assertNotNull($this->extension());

        // Once the dependent is gone, the dependency can go too.
        $this->modules->delete('acme/needs-hello', 'acme/needs-hello');
        $this->actingAsConfirmed($this->owner())->deleteRequest($this->extension())->assertSessionHasNoErrors();
        $this->assertNull($this->extension());
    }

    public function test_the_typed_name_is_required()
    {
        $hello = $this->extension();

        $this->actingAsConfirmed($this->owner())->deleteRequest($hello, 'yes')->assertSessionHasErrors('module');

        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
    }

    public function test_protected_first_party_modules_cannot_be_deleted()
    {
        $this->makeModule('modrixcommerce/cloud-connector', []);
        $connector = $this->extension('modrixcommerce/cloud-connector');

        $this->actingAsConfirmed($this->owner())->get(route('admin.extensions.show', $connector))
            ->assertInertia(fn (Assert $page) => $page->where('extension.protected', true));

        $this->actingAsConfirmed($this->owner())->deleteRequest($connector)
            ->assertSessionHasErrors(['module' => 'modrixcommerce/cloud-connector is part of Modrix Commerce and cannot be deleted. Disable it instead.']);

        $this->assertDirectoryExists($this->modulesPath.'/modrixcommerce/cloud-connector');
        $this->assertNotNull($this->extension('modrixcommerce/cloud-connector'));
    }

    public function test_deleting_needs_permission_and_a_recent_password_confirmation()
    {
        $hello = $this->extension();

        $this->actingAs($this->owner())->deleteRequest($hello)->assertRedirect(route('password.confirm'));
        $this->actingAsConfirmed($this->userWithRole('analyst'))->deleteRequest($hello)->assertForbidden();
        $this->actingAsConfirmed($this->userWithRole('store-manager'))->deleteRequest($hello)->assertForbidden();

        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
        $this->assertNotNull($this->extension());
    }
}
