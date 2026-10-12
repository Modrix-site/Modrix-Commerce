<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_page_shows_checks_without_secrets()
    {
        $this->installPlatform();

        $response = $this->actingAs($this->owner())->get(route('admin.system.status'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/status')
            ->has('environment')
            ->has('checks'));

        $content = $response->getContent();
        $this->assertStringNotContainsString((string) config('app.key'), $content);
        $this->assertStringNotContainsString(str_replace('\\', '\\\\', base_path()), $content);
    }
}
