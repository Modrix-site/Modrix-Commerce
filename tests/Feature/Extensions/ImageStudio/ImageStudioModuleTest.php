<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ImageStudioModuleTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
    }

    public function test_module_installs_and_enables(): void
    {
        $this->assertDatabaseHas('extensions', ['name' => self::STUDIO_MODULE, 'state' => 'enabled']);
        $this->assertTrue(Schema::hasTable('image_studio_variants'));
    }

    public function test_settings_defaults(): void
    {
        $settings = app(ImageStudioSettings::class);

        $this->assertTrue($settings->enabled());
        $this->assertTrue($settings->processOnUpload());
        $this->assertTrue($settings->removeBackground());
        $this->assertSame('gd-edge', $settings->driver());
        $this->assertSame(18, $settings->tolerance());
        $this->assertSame(1, $settings->featherPx());
        $this->assertSame(82, $settings->webpQuality());
        $this->assertNull($settings->removeBgKey());
        $this->assertFalse($settings->hasRemoveBgKey());
    }

    public function test_schema_rejects_out_of_range_tolerance(): void
    {
        $manifest = app(ModuleRepository::class)->find(self::STUDIO_MODULE)->manifest();

        $this->expectException(ValidationException::class);
        app(ModuleSettingsSchema::class)->save($manifest, ['tolerance' => 500]);
    }

    public function test_settings_clamp_values_written_directly(): void
    {
        $prefix = 'extension.modrixcommerce.image-studio';
        app(SettingsRepository::class)->setMany([
            "{$prefix}.tolerance" => 500,
            "{$prefix}.feather_px" => 99,
            "{$prefix}.webp_quality" => 5,
            "{$prefix}.driver" => 'bogus',
        ]);

        $settings = app(ImageStudioSettings::class);

        $this->assertSame(100, $settings->tolerance());
        $this->assertSame(4, $settings->featherPx());
        $this->assertSame(40, $settings->webpQuality());
        $this->assertSame('gd-edge', $settings->driver());
    }

    public function test_remove_bg_key_is_stored_as_a_secret(): void
    {
        $settings = app(ImageStudioSettings::class);
        $settings->setRemoveBgKey('abcd1234efgh');

        $this->assertTrue($settings->hasRemoveBgKey());
        $this->assertSame('abcd1234efgh', $settings->removeBgKey());

        $settings->setRemoveBgKey(null);
        $this->assertFalse($settings->hasRemoveBgKey());
    }

    public function test_variant_belongs_to_image_and_cascades(): void
    {
        $image = $this->studioImage();

        $variant = ImageVariant::query()->create([
            'product_image_id' => $image->id,
            'status' => ImageVariant::DONE,
            'disk' => 'public',
            'path' => 'products/1/studio/x.webp',
        ]);

        $this->assertTrue($variant->image->is($image));
        $this->assertStringEndsWith('products/1/studio/x.webp', (string) $variant->url());

        $image->delete();
        $this->assertDatabaseMissing('image_studio_variants', ['id' => $variant->id]);
    }

    public function test_fixture_draws_default_square_and_alpha_helper(): void
    {
        $image = $this->studioImage('png');
        $bytes = \Storage::disk('public')->get($image->path);

        $this->assertSame(0, $this->pixelAlpha($bytes, 100, 100));
    }

    public function test_setting_changes_are_visible_after_scoped_instances_are_forgotten(): void
    {
        $settings = app(ImageStudioSettings::class);
        $this->assertSame(18, $settings->tolerance());
        $this->assertFalse($settings->hasRemoveBgKey());

        // Another process (the admin request) saves new values with its own repository.
        $other = new SettingsRepository($this->app->make('cache.store'));
        $other->setMany(['extension.modrixcommerce.image-studio.tolerance' => 40]);
        app(SecretSettings::class)->put('image_studio.removebg_api_key', 'k-123456789');

        // What the queue worker does between jobs.
        app()->forgetScopedInstances();

        $fresh = app(ImageStudioSettings::class);
        $this->assertNotSame($settings, $fresh);
        $this->assertSame(40, $fresh->tolerance());
        $this->assertTrue($fresh->hasRemoveBgKey());
    }
}
