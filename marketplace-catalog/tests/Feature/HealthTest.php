<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_the_app_boots()
    {
        $this->get('/up')->assertOk();
    }
}
