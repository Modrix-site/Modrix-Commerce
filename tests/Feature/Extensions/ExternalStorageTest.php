<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Actions\StoreProductImage;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Models\User;
use EcoModular\ExternalStorage\ExternalStorageServiceProvider;
use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Services\ExternalStorageClient;
use EcoModular\ExternalStorage\Services\MediaOffloadService;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use EcoModular\ExternalStorage\Support\ProviderSetupGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ExternalStorageTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/external-storage';

    private ModuleManager $modules;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(
            base_path('modules/modrixcommerce/external-storage'),
            $this->modulesPath.'/modrixcommerce/external-storage'
        );

        $this->modules = app(ModuleManager::class);
        $this->modules->install(self::MODULE);
        $this->modules->enable(self::MODULE, true);
        config(['inertia.ssr.enabled' => false]);

        $this->owner = $this->owner();
    }

    public function test_module_installs_and_is_compatible_with_core_app_store(): void
    {
        $discovered = app(ModuleRepository::class)->find(self::MODULE);

        $this->assertNotNull($discovered);
        $this->assertSame(ModuleState::Enabled, $discovered->state);
        $this->assertSame('1.0.0', $discovered->manifest()->version);
        $this->assertContains('catalog.image_urls', $discovered->manifest()->capabilities);
        $this->assertContains('storefront.slots', $discovered->manifest()->capabilities);

        // Verify all 11 provider setup & configuration guides are available once enabled
        $guides = ProviderSetupGuides::all();
        $this->assertCount(11, $guides);
        foreach (array_keys(ExternalStorageSettings::PROVIDERS) as $providerKey) {
            $this->assertArrayHasKey($providerKey, $guides);
            $this->assertNotEmpty($guides[$providerKey]['steps']);
            $this->assertNotEmpty($guides[$providerKey]['required_fields']);
        }

        $this->actingAs($this->owner)
            ->get('/admin/m/modrixcommerce/external-storage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/external-storage/admin/index', false)
                ->has('providerGuides.wasabi.steps')
                ->has('providerGuides.s3.steps')
                ->has('providerGuides.cloudflare_r2.steps')
                ->has('providerGuides.cloudflare_api.steps')
                ->has('providerGuides.bunnycdn.steps')
            );
    }

    public function test_admin_can_configure_wasabi_s3_and_cloudflare_api_settings_and_test_connection(): void
    {
        Http::fake([
            'https://s3.eu-central-1.wasabisys.com/*' => Http::response('', 200),
            'https://api.cloudflare.com/client/v4/accounts/*' => Http::response(['success' => true], 200),
        ]);

        // 1. Configure Wasabi Hot Cloud Storage
        $response = $this->actingAs($this->owner)->put('/admin/m/modrixcommerce/external-storage/settings', [
            'enabled' => true,
            'provider' => 'wasabi',
            'save_mode' => 'external_only',
            'auto_offload_photos' => true,
            'auto_offload_videos' => true,
            'delete_local_after_sync' => true,
            'show_product_videos' => true,
            'bucket' => 'babylane-wasabi-media',
            'region' => 'eu-central-1',
            'account_id' => '',
            'endpoint' => '',
            'cdn_url' => '',
            'path_prefix' => 'catalog',
            'access_key' => 'WASABIKEY123456',
            'secret_key' => 'WASABISECRET987654321',
        ]);

        $response->assertRedirect();

        $settings = app(ExternalStorageSettings::class);
        $this->assertSame('wasabi', $settings->provider());
        $this->assertSame('external_only', $settings->saveMode());
        $this->assertTrue($settings->isConfigured());
        $this->assertSame('••••3456', $settings->accessKeyHint());

        $probe = app(ExternalStorageClient::class)->testConnection();
        $this->assertTrue($probe['ok']);

        // 2. Configure Cloudflare Storage via REST API
        $this->actingAs($this->owner)->put('/admin/m/modrixcommerce/external-storage/settings', [
            'enabled' => true,
            'provider' => 'cloudflare_api',
            'save_mode' => 'external_only',
            'auto_offload_photos' => true,
            'auto_offload_videos' => true,
            'delete_local_after_sync' => true,
            'show_product_videos' => true,
            'bucket' => 'cloudflare-media-bucket',
            'region' => 'auto',
            'account_id' => 'cf_acc_9988776655',
            'endpoint' => '',
            'cdn_url' => 'https://media.babylane.store',
            'path_prefix' => 'catalog',
            'api_token' => 'cf_bearer_token_secret_1234',
        ])->assertRedirect();

        $settings = app(ExternalStorageSettings::class);
        $this->assertSame('cloudflare_api', $settings->provider());
        $this->assertTrue($settings->isConfigured());

        $cfProbe = app(ExternalStorageClient::class)->testConnection();
        $this->assertTrue($cfProbe['ok']);
    }

    public function test_product_photos_save_automatically_in_external_storage_instead_of_locally(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://s3.us-east-1.amazonaws.com/*' => Http::response('', 200),
            'https://s3.wasabisys.com/*' => Http::response('', 200),
        ]);

        // Configure AWS S3 with 'external_only' mode (save automatically in external storage instead of locally)
        $settings = app(ExternalStorageSettings::class);
        $settings->saveConfiguration([
            'enabled' => true,
            'provider' => 's3',
            'save_mode' => 'external_only',
            'auto_offload_photos' => true,
            'auto_offload_videos' => true,
            'delete_local_after_sync' => true,
            'show_product_videos' => true,
            'bucket' => 'my-s3-product-bucket',
            'region' => 'us-east-1',
            'path_prefix' => 'catalog',
            'cdn_url' => 'https://cdn.example-s3.com',
            'access_key' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        ]);

        // Re-run automatic disk configuration
        app(ExternalStorageServiceProvider::class, ['app' => $this->app])->configureAutomaticExternalDisk();
        $this->assertSame('external_storage', config('ecomodular.media_disk'));

        $product = $this->sellable(product: ['name' => 'Cloud Camera Lens'])->product;

        // Store a product image using core StoreProductImage action
        $fakeUpload = UploadedFile::fake()->image('camera-lens.jpg', 600, 600);
        $productImage = app(StoreProductImage::class)->handle($product, $fakeUpload, 'Camera Lens Alt');

        // Verify it was saved to external_storage disk and NOT stored on local 'public' disk
        $this->assertSame('external_storage', $productImage->disk);
        Storage::disk('public')->assertMissing($productImage->path);

        // Verify ExternalStorageFile record was automatically created and marked local_deleted = true
        $externalRecord = ExternalStorageFile::query()->where('product_image_id', $productImage->id)->first();
        $this->assertNotNull($externalRecord);
        $this->assertSame('s3', $externalRecord->provider);
        $this->assertSame('synced', $externalRecord->status);
        $this->assertTrue($externalRecord->local_deleted);

        // Verify ProductImage::url() resolves to the external CDN URL via ProductImageUrls
        $this->assertStringStartsWith('https://cdn.example-s3.com/catalog/products/', $productImage->url());
    }

    public function test_direct_upload_of_product_video_and_photo_to_cloudflare_and_wasabi(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/*' => Http::response(['success' => true], 200),
            'https://s3.us-east-1.wasabisys.com/*' => Http::response('', 200),
            'https://s3.wasabisys.com/*' => Http::response('', 200),
        ]);

        $settings = app(ExternalStorageSettings::class);
        $settings->saveConfiguration([
            'enabled' => true,
            'provider' => 'cloudflare_api',
            'save_mode' => 'external_only',
            'auto_offload_photos' => true,
            'auto_offload_videos' => true,
            'delete_local_after_sync' => true,
            'show_product_videos' => true,
            'bucket' => 'cf-r2-media',
            'region' => 'auto',
            'account_id' => 'cf_account_123',
            'cdn_url' => 'https://pub-r2.cloudflare-cdn.com',
            'path_prefix' => 'catalog',
            'api_token' => 'cf_api_token_secret_xyz',
        ]);

        $product = $this->sellable(product: ['name' => 'Smart Stroller Pro'])->product;

        // 1. Upload a Product Video (.mp4) directly to Cloudflare Storage via API
        $videoFile = UploadedFile::fake()->create('stroller-demo.mp4', 2048, 'video/mp4');

        $this->actingAs($this->owner)->post('/admin/m/modrixcommerce/external-storage/upload', [
            'file' => $videoFile,
            'product_id' => $product->id,
            'title' => 'Smart Stroller 360 Video',
            'media_type' => 'video',
        ])->assertRedirect();

        $videoRecord = ExternalStorageFile::query()
            ->where('product_id', $product->id)
            ->where('media_type', ExternalStorageFile::TYPE_VIDEO)
            ->first();

        $this->assertNotNull($videoRecord);
        $this->assertSame('cloudflare_api', $videoRecord->provider);
        $this->assertSame('Smart Stroller 360 Video', $videoRecord->title);
        $this->assertTrue($videoRecord->local_deleted);
        $this->assertStringStartsWith('https://pub-r2.cloudflare-cdn.com/catalog/products/videos/', $videoRecord->publicUrl());

        // Verify local 'public' disk has no copy of the video
        Storage::disk('public')->assertMissing($videoRecord->local_path);

        // Verify Storefront Slot `product.after_details` renders the Product Video Gallery
        $renderedSlots = app(StorefrontSlots::class)->render('product.after_details', ['product' => $product]);
        $videoSlot = collect($renderedSlots)->firstWhere('component', 'modrixcommerce/external-storage/ProductExternalMediaSlot');

        $this->assertNotNull($videoSlot);
        $this->assertCount(1, $videoSlot['props']['videos']);
        $this->assertSame('Smart Stroller 360 Video', $videoSlot['props']['videos'][0]['title']);

        // 2. Upload a Product Photo (.png) directly to external storage
        $photoFile = UploadedFile::fake()->image('stroller-side.png', 500, 500);

        $this->actingAs($this->owner)->post('/admin/m/modrixcommerce/external-storage/upload', [
            'file' => $photoFile,
            'product_id' => $product->id,
            'title' => 'Stroller Side View',
            'media_type' => 'photo',
        ])->assertRedirect();

        $photoRecord = ExternalStorageFile::query()
            ->where('product_id', $product->id)
            ->where('media_type', ExternalStorageFile::TYPE_PHOTO)
            ->first();

        $this->assertNotNull($photoRecord);
        $this->assertNotNull($photoRecord->product_image_id);
        $productImage = ProductImage::find($photoRecord->product_image_id);
        $this->assertNotNull($productImage);
        $this->assertSame($photoRecord->publicUrl(), $productImage->url());
    }

    public function test_bulk_sync_offloads_existing_local_product_images_to_wasabi_and_removes_local_copy(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://s3.us-west-1.wasabisys.com/*' => Http::response('', 200),
        ]);

        // Create a local product image BEFORE external storage is configured
        $product = $this->sellable(product: ['name' => 'Legacy Local Product'])->product;
        $localPath = "products/{$product->id}/legacy-photo.jpg";
        Storage::disk('public')->put($localPath, 'fake-jpeg-binary-content');

        $image = ProductImage::withoutEvents(fn () => ProductImage::query()->forceCreate([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $localPath,
            'alt' => 'Legacy Photo',
            'position' => 1,
        ]));

        Storage::disk('public')->assertExists($localPath);

        // Now configure Wasabi external storage with delete_local_after_sync = true
        app(ExternalStorageSettings::class)->saveConfiguration([
            'enabled' => true,
            'provider' => 'wasabi',
            'save_mode' => 'external_only',
            'auto_offload_photos' => true,
            'auto_offload_videos' => true,
            'delete_local_after_sync' => true,
            'show_product_videos' => true,
            'bucket' => 'wasabi-prod-bucket',
            'region' => 'us-west-1',
            'path_prefix' => 'catalog',
            'access_key' => 'WASABI_KEY_001',
            'secret_key' => 'WASABI_SECRET_001',
        ]);

        // Trigger bulk sync
        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/external-storage/sync-all')
            ->assertRedirect();

        // Local copy was removed after sync, and ExternalStorageFile was created
        Storage::disk('public')->assertMissing($localPath);

        $synced = ExternalStorageFile::query()->where('product_image_id', $image->id)->first();
        $this->assertNotNull($synced);
        $this->assertSame('wasabi', $synced->provider);
        $this->assertTrue($synced->local_deleted);

        app(MediaOffloadService::class)->forgetCache();
        $this->assertStringContainsString('s3.us-west-1.wasabisys.com/wasabi-prod-bucket/catalog/', $image->fresh()->url());
    }
}
