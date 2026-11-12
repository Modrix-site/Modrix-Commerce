<?php

namespace Tests\Feature\Install;

use App\Core\Installation\InstallEnvironment;
use App\Core\Installation\InstallMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallModeDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function environment(): InstallEnvironment
    {
        return app(InstallEnvironment::class);
    }

    public function test_migrated_database_detects_auto()
    {
        $this->assertSame(InstallMode::Auto, $this->environment()->mode());
        $this->assertNull($this->environment()->databaseProblem());
        $this->assertTrue($this->environment()->databaseReady());
    }

    public function test_pending_migrations_detect_manual()
    {
        DB::table('migrations')->delete();

        $this->assertSame(InstallMode::Manual, $this->environment()->mode());
        $this->assertSame('not_migrated', $this->environment()->databaseProblem());
    }

    public function test_unreachable_database_detects_manual()
    {
        $previous = config('database.default');
        config(['database.connections.unreachable' => [
            'driver' => 'sqlite',
            'database' => '/no/such/dir/x.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ], 'database.default' => 'unreachable']);

        try {
            $this->assertSame('unreachable', $this->environment()->databaseProblem());
            $this->assertSame(InstallMode::Manual, $this->environment()->mode());
        } finally {
            config(['database.default' => $previous]);
        }
    }

    public function test_empty_database_name_is_not_configured()
    {
        $previous = config('database.default');
        config(['database.connections.blank' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => ''], 'database.default' => 'blank']);

        try {
            $this->assertSame('not_configured', $this->environment()->databaseProblem());
            $this->assertSame(InstallMode::Manual, $this->environment()->mode());
        } finally {
            config(['database.default' => $previous]);
        }
    }

    public function test_mode_can_be_forced()
    {
        config(['ecomodular.installer.mode' => 'manual']);
        $this->assertSame(InstallMode::Manual, $this->environment()->mode());

        config(['ecomodular.installer.mode' => 'auto']);
        DB::table('migrations')->delete();
        $this->assertSame(InstallMode::Auto, $this->environment()->mode());
        $this->assertSame('not_migrated', $this->environment()->databaseProblem());
    }

    public function test_served_from_public_checks_the_front_controller()
    {
        $public = Request::create('/install', server: ['SCRIPT_FILENAME' => public_path('index.php'), 'DOCUMENT_ROOT' => public_path()]);
        $root = Request::create('/install', server: ['SCRIPT_FILENAME' => base_path('index.php'), 'DOCUMENT_ROOT' => base_path()]);

        $this->assertTrue($this->environment()->servedFromPublic($public));
        $this->assertFalse($this->environment()->servedFromPublic($root));
    }

    public function test_project_inside_the_document_root_is_refused_even_through_public()
    {
        // cPanel upload into public_html, browsed at /public/install: .env would be downloadable.
        $viaPublic = Request::create('/public/install', server: [
            'SCRIPT_FILENAME' => public_path('index.php'),
            'SCRIPT_NAME' => '/public/index.php',
            'DOCUMENT_ROOT' => base_path(),
        ]);
        $parent = Request::create('/install', server: ['SCRIPT_FILENAME' => public_path('index.php'), 'DOCUMENT_ROOT' => dirname(base_path())]);
        $unknown = Request::create('/install', server: ['SCRIPT_FILENAME' => public_path('index.php'), 'DOCUMENT_ROOT' => '']);

        $this->assertFalse($this->environment()->servedFromPublic($viaPublic));
        $this->assertFalse($this->environment()->servedFromPublic($parent));
        $this->assertFalse($this->environment()->servedFromPublic($unknown));
    }

    public function test_configured_database_password_is_exposed_for_proof_checks()
    {
        config(['database.connections.'.config('database.default').'.password' => 'deploy-pass']);

        $this->assertSame('deploy-pass', $this->environment()->databasePasswordConfigured());
    }
}
