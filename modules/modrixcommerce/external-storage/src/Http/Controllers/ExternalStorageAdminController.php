<?php

namespace EcoModular\ExternalStorage\Http\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Services\ExternalStorageClient;
use EcoModular\ExternalStorage\Services\MediaOffloadService;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use EcoModular\ExternalStorage\Support\ProviderSetupGuides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ExternalStorageAdminController extends Controller
{
    public function __construct(
        private readonly ExternalStorageSettings $settings,
        private readonly ExternalStorageClient $client,
        private readonly MediaOffloadService $service,
    ) {}

    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $providerFilter = $request->string('provider')->toString();

        $query = ExternalStorageFile::query()
            ->with(['product' => fn ($q) => $q->withCurrentTranslation()])
            ->orderByDesc('id');

        if (in_array($type, [ExternalStorageFile::TYPE_PHOTO, ExternalStorageFile::TYPE_VIDEO, ExternalStorageFile::TYPE_DOCUMENT], true)) {
            $query->where('media_type', $type);
        }

        if (in_array($status, [ExternalStorageFile::STATUS_SYNCED, ExternalStorageFile::STATUS_PENDING, ExternalStorageFile::STATUS_FAILED], true)) {
            $query->where('status', $status);
        }

        if ($providerFilter !== '' && array_key_exists($providerFilter, ExternalStorageSettings::PROVIDERS)) {
            $query->where('provider', $providerFilter);
        }

        $files = $query->paginate(20)->withQueryString()->through(fn (ExternalStorageFile $file) => [
            'id' => $file->id,
            'product_id' => $file->product_id,
            'product_name' => $file->product?->localized('name') ?: $file->product?->name,
            'product_image_id' => $file->product_image_id,
            'media_type' => $file->media_type,
            'title' => $file->title,
            'provider' => $file->provider,
            'provider_label' => $this->settings->providerLabel($file->provider),
            'bucket' => $file->bucket,
            'remote_path' => $file->remote_path,
            'remote_url' => $file->publicUrl(),
            'filename' => $file->filename,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'local_deleted' => $file->local_deleted,
            'status' => $file->status,
            'error_message' => $file->error_message,
            'synced_at' => $file->synced_at?->toIso8601String(),
        ]);

        $products = Product::query()
            ->withCurrentTranslation()
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->localized('name') ?: $p->name,
            ])
            ->values()
            ->all();

        $providers = collect(ExternalStorageSettings::PROVIDERS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        return Inertia::render('module/modrixcommerce/external-storage/admin/index', [
            'settings' => $this->settings->toAdminProps(),
            'providers' => $providers,
            'providerGuides' => ProviderSetupGuides::all(),
            'stats' => $this->service->stats(),
            'files' => $files,
            'products' => $products,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'provider' => $providerFilter,
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(ExternalStorageSettings::PROVIDERS))],
            'save_mode' => ['required', 'string', 'in:external_only,hybrid,manual'],
            'auto_offload_photos' => ['required', 'boolean'],
            'auto_offload_videos' => ['required', 'boolean'],
            'delete_local_after_sync' => ['required', 'boolean'],
            'show_product_videos' => ['required', 'boolean'],
            'bucket' => ['nullable', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', 'string', 'max:191'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'cdn_url' => ['nullable', 'string', 'max:255'],
            'path_prefix' => ['nullable', 'string', 'max:120'],
            'access_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:500'],
            'clear_access_key' => ['nullable', 'boolean'],
            'clear_secret_key' => ['nullable', 'boolean'],
            'clear_api_token' => ['nullable', 'boolean'],
        ]);

        $this->settings->saveConfiguration($validated);
        $this->service->forgetCache();

        return back()->with('success', 'External Cloud Storage settings saved.');
    }

    public function testConnection(): RedirectResponse
    {
        $result = $this->client->testConnection();

        if ($result['ok']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:102400', // Up to 100 MB for product videos and high-res photos
                'mimes:jpg,jpeg,png,webp,gif,avif,mp4,webm,mov,m4v,ogg',
            ],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'media_type' => ['nullable', 'string', 'in:photo,video'],
        ]);

        if (! $this->settings->isConfigured()) {
            return back()->with('error', 'Please configure your external storage provider credentials and bucket before uploading.');
        }

        try {
            $record = $this->service->uploadDirectFile(
                file: $request->file('file'),
                productId: isset($validated['product_id']) ? (int) $validated['product_id'] : null,
                title: $validated['title'] ?? null,
                forcedMediaType: $validated['media_type'] ?? null,
            );

            $kind = $record->isVideo() ? 'Product video' : 'Product photo';
            $modeNote = $record->local_deleted ? 'saved directly in external storage (no local copy)' : 'saved in external storage';

            return back()->with('success', "{$kind} {$modeNote} via {$this->settings->providerLabel($record->provider)}.");
        } catch (Throwable $e) {
            return back()->with('error', 'Upload failed: '.$e->getMessage());
        }
    }

    public function syncAll(): RedirectResponse
    {
        if (! $this->settings->isConfigured()) {
            return back()->with('error', 'Please configure your external storage provider credentials and bucket first.');
        }

        $result = $this->service->syncLocalImages();

        if ($result['failed'] > 0 && $result['synced'] === 0) {
            return back()->with('error', "Failed to sync {$result['failed']} image(s) to external storage.");
        }

        return back()->with(
            'success',
            "Synced {$result['synced']} product photo(s) to {$this->settings->providerLabel()}."
            .($result['failed'] > 0 ? " ({$result['failed']} failed)" : '')
        );
    }

    public function syncProduct(Product $product): RedirectResponse
    {
        if (! $this->settings->isConfigured()) {
            return back()->with('error', 'Please configure your external storage provider credentials and bucket first.');
        }

        $result = $this->service->syncLocalImages($product->id);

        return back()->with(
            'success',
            "Synced {$result['synced']} photo(s) for this product to {$this->settings->providerLabel()}."
        );
    }

    public function destroy(Request $request, ExternalStorageFile $file): RedirectResponse
    {
        $deleteProductImage = $request->boolean('delete_product_image', false);
        $this->service->deleteExternalFile($file, $deleteProductImage);

        return back()->with('success', 'External storage file removed.');
    }
}
