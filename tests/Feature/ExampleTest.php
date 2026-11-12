<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_once_installed()
    {
        $this->installPlatform();

        $this->get(route('home'))->assertOk();
    }

    public function test_home_page_redirects_to_installer_before_installation()
    {
        $this->get(route('home'))->assertRedirect(route('install.show'));
    }
}
