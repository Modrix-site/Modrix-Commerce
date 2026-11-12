<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\SecretSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Dashboard and Admin → Extensions invite the owner to connect to Modrix
 * Cloud (every theme and module, free and paid) until the store is connected.
 */
class ModrixCloudPromptTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
        $this->setUpModules();
    }

    private function copyConnector(): void
    {
        File::copyDirectory(base_path('modules/modrixcommerce/cloud-connector'), $this->modulesPath.'/modrixcommerce/cloud-connector');
        ModuleRepository::flushBootCache();
        app(ModuleRepository::class)->sync();
    }

    public function test_a_disabled_connector_is_offered_first()
    {
        $this->copyConnector();
        $connector = app(ModuleRepository::class)->find('modrixcommerce/cloud-connector');

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cloudPrompt.state', 'disabled')
                ->where('cloudPrompt.url', route('admin.extensions.show', $connector)));
        $this->get(route('admin.extensions.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt.state', 'disabled'));
    }

    public function test_an_enabled_but_unconnected_store_is_asked_to_connect()
    {
        $this->enableBundledModule('modrixcommerce/cloud-connector');

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cloudPrompt.state', 'disconnected')
                ->where('cloudPrompt.url', route('admin.modrixcommerce.cloud-connector.index')));
        $this->get(route('admin.extensions.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt.state', 'disconnected'));
    }

    public function test_the_prompt_disappears_once_connected()
    {
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        app(SecretSettings::class)->put('cloud.connection_token', str_repeat('a', 64));

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt', null));
        $this->get(route('admin.extensions.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt', null));
    }

    public function test_staff_who_cannot_manage_extensions_do_not_see_it()
    {
        $this->enableBundledModule('modrixcommerce/cloud-connector');

        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt', null));
    }

    public function test_no_prompt_when_the_connector_is_not_on_disk()
    {
        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt', null));
    }

    public function test_disabling_the_connector_again_brings_back_the_enable_step()
    {
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        app(ModuleManager::class)->disable('modrixcommerce/cloud-connector');

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('cloudPrompt.state', 'disabled'));
    }
}
