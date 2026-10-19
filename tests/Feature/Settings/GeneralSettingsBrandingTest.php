<?php

namespace Tests\Feature\Settings;

use App\Core\Audit\AuditLog;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GeneralSettingsBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        Storage::fake(config('ecomodular.media_disk', 'public'));
    }

    private function disk()
    {
        return Storage::disk(config('ecomodular.media_disk', 'public'));
    }

    public function test_the_page_shows_every_section_to_settings_managers()
    {
        $this->actingAs($this->owner())->get(route('admin.settings.general.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/general')
            ->has('branding')
            ->has('mail')
            ->has('maintenance')
            ->has('security')
            ->has('social')
            ->has('scripts')
            ->where('canAdvanced', true));
    }

    public function test_settings_managers_without_the_advanced_permission_see_it_read_only()
    {
        $this->roleWithPermissions('settings-only', ['settings.manage']);
        $staff = $this->userWithRole('settings-only');

        $this->actingAs($staff)->get(route('admin.settings.general.edit'))->assertInertia(fn (Assert $page) => $page->where('canAdvanced', false));
        $this->actingAs($staff)->put(route('admin.settings.general.security'), ['require_two_factor' => false, 'session_lifetime' => 120, 'login_attempts' => 5, 'password_min' => 8])->assertForbidden();
        $this->actingAs($staff)->put(route('admin.settings.general.scripts'), ['head_enabled' => false, 'head' => '', 'body_enabled' => false, 'body' => '', 'on_checkout' => false, 'hosts' => []])->assertForbidden();
    }

    public function test_customers_cannot_open_general_settings()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.general.edit'))->assertForbidden();
    }

    public function test_the_admin_uses_the_store_name_as_its_only_name()
    {
        // A panel name saved before there was one name only is ignored.
        app(SettingsRepository::class)->set('general.branding', ['panel_name' => 'Old Admin Name']);
        app(SettingsRepository::class)->set('store.name', 'Jhay Store');

        $this->actingAs($this->owner())->post(route('admin.settings.general.branding'), ['panel_name' => 'Other Name'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('branding.panel_name', 'Jhay Store'));
        $this->assertArrayNotHasKey('panel_name', app(GeneralSettings::class)->branding());
    }

    public function test_branding_saves_texts_and_images_and_shares_them()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.settings.general.branding'), [
            'accent' => '#1d4ed8',
            'login_message' => 'Staff sign-in only',
            'logo' => UploadedFile::fake()->image('logo.png', 400, 120),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $branding = app(GeneralSettings::class)->branding();
        $this->disk()->assertExists($branding['logo']);
        $this->disk()->assertExists($branding['favicon']);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('branding.accent', '#1d4ed8')
            ->where('branding.logo', fn ($url) => is_string($url) && str_contains($url, 'branding/')));
        $this->assertTrue(AuditLog::query()->where('action', 'settings.general_updated')->exists());
    }

    public function test_replacing_or_removing_an_image_deletes_the_old_file()
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['logo' => UploadedFile::fake()->image('a.png')]);
        $first = app(GeneralSettings::class)->branding()['logo'];

        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['logo' => UploadedFile::fake()->image('b.png')]);
        $second = app(GeneralSettings::class)->branding()['logo'];
        $this->disk()->assertMissing($first);
        $this->disk()->assertExists($second);

        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['remove' => ['logo']]);
        $this->assertNull(app(GeneralSettings::class)->branding()['logo']);
        $this->disk()->assertMissing($second);
    }

    public function test_svg_and_oversized_files_and_bad_colours_are_refused()
    {
        $owner = $this->owner();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['logo' => $svg])->assertSessionHasErrors('logo');
        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['logo' => UploadedFile::fake()->image('big.png')->size(2048)])->assertSessionHasErrors('logo');
        $this->actingAs($owner)->post(route('admin.settings.general.branding'), ['accent' => 'red; background:url(x)'])->assertSessionHasErrors('accent');
        $this->assertNull(app(GeneralSettings::class)->branding()['logo']);
    }

    public function test_the_login_page_shows_the_login_logo_and_message()
    {
        $this->actingAs($this->owner())->post(route('admin.settings.general.branding'), ['login_message' => 'Staff sign-in only']);
        auth()->logout();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('branding.login_message', 'Staff sign-in only'));
    }
}
