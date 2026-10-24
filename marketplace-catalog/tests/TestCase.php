<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Private files go to a fresh temporary directory per test.
        $dir = sys_get_temp_dir().'/marketplace-test-'.bin2hex(random_bytes(6));
        config(['marketplace.storage' => $dir]);

        // Installed unless a test says otherwise (InstallerTest).
        @mkdir($dir, 0777, true);
        touch($dir.'/installed');
        config(['marketplace.installed_file' => $dir.'/installed']);
        $this->beforeApplicationDestroyed(fn () => $this->app['files']->deleteDirectory($dir));
    }
}
