<?php

namespace Tests\Feature\Admin;

use App\Core\Platform;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class CoreVersionTest extends TestCase
{
    use CommerceFixtures, DatabaseMigrations;

    public function test_version_is_shown_in_system_status_and_shared()
    {
        $this->installPlatform();

        $this->actingAs($this->owner())->get(route('admin.system.status'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('environment.0.label', 'Modrix Commerce')
                ->where('environment.0.value', Platform::VERSION)
                ->where('platform.version', Platform::VERSION));
    }
}
