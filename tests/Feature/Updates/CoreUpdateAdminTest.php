<?php

namespace Tests\Feature\Updates;

use App\Core\Audit\AuditLog;
use App\Core\Operations\HealthCheck;
use App\Core\Platform;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreReleaseVerifier;
use App\Core\Updates\CoreUpdateLauncher;
use App\Core\Updates\CoreUpdater;
use App\Core\Updates\CoreUpdateRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CoreReleaseFixtures;
use Tests\TestCase;

class CoreUpdateAdminTest extends TestCase
{
    use CoreReleaseFixtures, RefreshDatabase;

    /** @var list<int> */
    public array $launched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->useTestSigningKey();

        $test = $this;
        $this->app->instance(CoreUpdateLauncher::class, new class($test) extends CoreUpdateLauncher
        {
            public function __construct(private readonly CoreUpdateAdminTest $test) {}

            public function launch(CoreUpdateRun $run): void
            {
                $this->test->launched[] = $run->id;
            }
        });
    }

    /** @return array<string, mixed> the raw signed item */
    private function storeAvailable(string $version = '1.1.0'): array
    {
        $item = $this->signedItem($version, str_repeat('a', 64), 1000);
        $verified = app(CoreReleaseVerifier::class)->verify($item);
        app(SettingsRepository::class)->set('core_update.available', $verified + ['item' => $item]);

        return $item;
    }

    private function connect(): void
    {
        app(SecretSettings::class)->put('cloud.connection_token', 'ct_'.str_repeat('a', 61));
    }

    /** `start` needs a recent password confirmation, like extension installs. */
    private function confirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_status_page_loads_before_the_runs_table_is_migrated(): void
    {
        $this->storeAvailable();
        $this->connect();
        \Illuminate\Support\Facades\Schema::drop('core_update_runs');

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('coreUpdate.available.version', '1.1.0')
                ->where('coreUpdate.active_run', null)
                ->where('coreUpdate.runs', [])
                ->where('coreUpdate.can_install', false));
    }

    public function test_status_page_shows_available_update(): void
    {
        $this->storeAvailable();
        $this->connect();

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('coreUpdate.available.version', '1.1.0')
                ->where('coreUpdate.available.min_php', '8.3')
                ->missing('coreUpdate.available.item')
                ->missing('coreUpdate.available.verification')
                ->where('coreUpdate.connected', true)
                ->where('coreUpdate.can_install', true)
                ->where('coreUpdate.active_run', null)
                ->has('coreUpdate.runs', 0)
                ->has('coreUpdate.current'));
    }

    public function test_status_page_cannot_install_when_not_connected(): void
    {
        $this->storeAvailable();

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('coreUpdate.connected', false)
                ->where('coreUpdate.can_install', false));
    }

    public function test_staff_without_permission_cannot_install(): void
    {
        $this->storeAvailable();
        $this->connect();
        $role = $this->roleWithPermissions('viewer', ['system.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['created_at' => now()]);
        $user = $user->fresh();

        $this->confirmed($user)->post(route('admin.system.core-update.start'))->assertForbidden();

        $this->assertSame(0, CoreUpdateRun::query()->count());
        $this->actingAs($user)->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page->where('coreUpdate.can_install', false));
    }

    public function test_start_creates_a_queued_run_and_launches_it(): void
    {
        $item = $this->storeAvailable();
        $this->connect();
        $owner = $this->owner();

        $this->confirmed($owner)->post(route('admin.system.core-update.start'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $run = CoreUpdateRun::query()->sole();
        $this->assertSame(CoreUpdateRun::QUEUED, $run->status);
        $this->assertSame('1.1.0', $run->to_version);
        $this->assertSame(Platform::VERSION, $run->from_version);
        $this->assertSame($item, $run->release);
        $this->assertSame($owner->id, $run->actor_id);
        $this->assertSame([$run->id], $this->launched);
        $this->assertTrue(AuditLog::query()->where('action', 'core.update_started')->exists());
    }

    public function test_start_requires_a_recent_password_confirmation(): void
    {
        $this->storeAvailable();
        $this->connect();

        $this->actingAs($this->owner())->from(route('admin.system.status'))->post(route('admin.system.core-update.start'))
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(0, CoreUpdateRun::query()->count());
        $this->assertSame([], $this->launched);
        // After confirming, the admin lands back on the status page (a POST is never replayed).
        $this->assertSame(route('admin.system.status'), session('url.intended'));
    }

    public function test_start_without_an_available_release_is_refused(): void
    {
        $this->connect();

        $this->confirmed($this->owner())->post(route('admin.system.core-update.start'))
            ->assertSessionHasErrors('core_update');

        $this->assertSame(0, CoreUpdateRun::query()->count());
        $this->assertSame([], $this->launched);
    }

    public function test_start_with_an_unverifiable_release_is_refused(): void
    {
        $item = $this->storeAvailable();
        $item['verification']['metadata']['sha256'] = str_repeat('0', 64);
        app(SettingsRepository::class)->set('core_update.available', ['version' => '1.1.0', 'item' => $item]);

        $this->confirmed($this->owner())->post(route('admin.system.core-update.start'))
            ->assertSessionHasErrors('core_update');

        $this->assertSame(0, CoreUpdateRun::query()->count());
    }

    public function test_second_install_is_refused_while_one_is_running(): void
    {
        $this->storeAvailable();
        $this->connect();
        CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::DOWNLOADING,
        ]);

        $this->confirmed($this->owner())->post(route('admin.system.core-update.start'))
            ->assertSessionHasErrors('core_update');

        $this->assertSame(1, CoreUpdateRun::query()->count());
        $this->assertSame([], $this->launched);
    }

    public function test_a_stale_run_does_not_block_a_new_install(): void
    {
        $this->storeAvailable();
        $this->connect();
        $stale = CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::INSTALLING,
        ]);
        CoreUpdateRun::query()->whereKey($stale->id)->update(['updated_at' => now()->subMinutes(CoreUpdateRun::STALE_MINUTES + 5)]);

        $this->confirmed($this->owner())->post(route('admin.system.core-update.start'))->assertSessionHasNoErrors();

        $this->assertSame(2, CoreUpdateRun::query()->count());
    }

    public function test_check_stores_the_newest_release(): void
    {
        config(['ecomodular.marketplace.url' => $this->coreCloudBase]);
        Http::fake([
            $this->coreCloudBase.'/api/marketplace/v1/core/releases*' => Http::response(['releases' => [$this->signedItem('1.3.0', str_repeat('a', 64), 1000)]]),
        ]);

        $this->actingAs($this->owner())->post(route('admin.system.core-update.check'))->assertRedirect();

        $this->assertSame('1.3.0', app(SettingsRepository::class)->get('core_update.available')['version']);
    }

    public function test_show_returns_run_status_as_json(): void
    {
        $run = CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::MIGRATING, 'message' => 'working',
        ]);

        $this->actingAs($this->owner())->getJson(route('admin.system.core-update.show', $run))
            ->assertOk()->assertExactJson(['status' => 'migrating', 'message' => 'working', 'to_version' => '1.1.0', 'hint' => null]);

        $this->actingAs(User::factory()->create())->getJson(route('admin.system.core-update.show', $run))->assertForbidden();
    }

    public function test_a_queued_run_that_never_started_shows_a_hint(): void
    {
        $run = CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::QUEUED,
        ]);
        $hint = "The update did not start. Run php artisan ecomodular:core-update {$run->id} on the server.";

        $this->actingAs($this->owner())->getJson(route('admin.system.core-update.show', $run))->assertJson(['hint' => null]);

        CoreUpdateRun::query()->whereKey($run->id)->update(['updated_at' => now()->subSeconds(CoreUpdateRun::START_TIMEOUT_SECONDS + 5)]);

        $this->actingAs($this->owner())->getJson(route('admin.system.core-update.show', $run))->assertJson(['hint' => $hint]);
        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page->where('coreUpdate.active_run.hint', $hint));
    }

    public function test_active_run_offers_the_maintenance_bypass_to_installers_only(): void
    {
        $run = CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::INSTALLING,
        ]);
        $secret = app(CoreUpdater::class)->bypassSecret($run);

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page->where('coreUpdate.active_run.bypass_url', url('/'.$secret)));

        $role = $this->roleWithPermissions('viewer', ['system.view']);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role->id, ['created_at' => now()]);

        $this->actingAs($viewer->fresh())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page->where('coreUpdate.active_run.bypass_url', null));
    }

    public function test_an_installed_or_older_release_is_not_offered(): void
    {
        $this->connect();
        app(SettingsRepository::class)->set('core_update.available', ['version' => Platform::VERSION, 'min_php' => '8.3', 'notes' => '']);

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('coreUpdate.available', null)
                ->where('coreUpdate.can_install', false));

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');
        $this->assertSame(HealthCheck::OK, $check['status']);
    }

    public function test_runs_that_need_attention_are_flagged_on_the_card(): void
    {
        CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::FAILED,
            'message' => 'could NOT be fully undone', 'needs_attention' => true,
        ]);

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page->where('coreUpdate.runs.0.needs_attention', true));
    }

    public function test_status_page_lists_recent_runs_and_the_active_one(): void
    {
        $this->storeAvailable();
        $this->connect();
        foreach (range(1, 6) as $i) {
            CoreUpdateRun::query()->forceCreate([
                'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::FAILED, 'message' => "m{$i}",
            ]);
        }
        $active = CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::BACKING_UP,
        ]);

        $this->actingAs($this->owner())->get(route('admin.system.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('coreUpdate.runs', 5)
                ->where('coreUpdate.active_run.id', $active->id)
                ->where('coreUpdate.active_run.status', 'backing_up')
                ->where('coreUpdate.can_install', false));
    }
}
