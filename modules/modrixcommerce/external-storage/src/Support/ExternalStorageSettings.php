<?php

namespace EcoModular\ExternalStorage\Support;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use EcoModular\ExternalStorage\ExternalStorageServiceProvider;

/**
 * Typed access to External Cloud Storage module settings and encrypted API credentials.
 */
final class ExternalStorageSettings
{
    public const PROVIDERS = [
        'wasabi' => 'Wasabi Hot Cloud Storage (S3)',
        's3' => 'Amazon Web Services (AWS S3)',
        'cloudflare_r2' => 'Cloudflare R2 Storage (S3 API)',
        'cloudflare_api' => 'Cloudflare Storage via REST API',
        'digitalocean' => 'DigitalOcean Spaces',
        'backblaze' => 'Backblaze B2 Cloud Storage',
        'bunnycdn' => 'BunnyCDN Edge Storage API',
        'gcs' => 'Google Cloud Storage',
        'minio' => 'MinIO Object Storage',
        'custom_s3' => 'Custom S3-Compatible Storage',
        'custom_api' => 'Custom HTTP REST Storage API',
    ];

    public const SAVE_MODES = [
        'external_only',
        'hybrid',
        'manual',
    ];

    private const SECRET_ACCESS_KEY = 'external_storage.access_key';
    private const SECRET_SECRET_KEY = 'external_storage.secret_key';
    private const SECRET_API_TOKEN = 'external_storage.api_token';

    public function __construct(
        private readonly ModuleSettingsSchema $schema,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return $this->bool('enabled', true);
    }

    public function provider(): string
    {
        $provider = $this->string('provider', 'wasabi');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'wasabi';
    }

    public function providerLabel(?string $provider = null): string
    {
        $key = $provider ?? $this->provider();

        return self::PROVIDERS[$key] ?? ucfirst($key);
    }

    public function saveMode(): string
    {
        $mode = $this->string('save_mode', 'external_only');

        return in_array($mode, self::SAVE_MODES, true) ? $mode : 'external_only';
    }

    /**
     * Whether new product photos and videos should be saved automatically in external storage.
     */
    public function shouldSaveAutomaticallyInExternal(): bool
    {
        return $this->enabled() && in_array($this->saveMode(), ['external_only', 'hybrid'], true);
    }

    /**
     * Whether local copies should be skipped or deleted once stored in external storage.
     */
    public function shouldSkipOrDeleteLocal(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->saveMode() === 'external_only' || $this->deleteLocalAfterSync();
    }

    public function autoOffloadPhotos(): bool
    {
        return $this->bool('auto_offload_photos', true);
    }

    public function autoOffloadVideos(): bool
    {
        return $this->bool('auto_offload_videos', true);
    }

    public function deleteLocalAfterSync(): bool
    {
        return $this->bool('delete_local_after_sync', true);
    }

    public function showProductVideos(): bool
    {
        return $this->bool('show_product_videos', true);
    }

    public function bucket(): string
    {
        return trim($this->string('bucket', ''), '/ ');
    }

    public function region(): string
    {
        $region = trim($this->string('region', 'us-east-1'));

        return $region !== '' ? $region : 'us-east-1';
    }

    public function accountId(): string
    {
        return trim($this->string('account_id', ''));
    }

    public function endpoint(): string
    {
        return rtrim(trim($this->string('endpoint', '')), '/');
    }

    public function cdnUrl(): string
    {
        return rtrim(trim($this->string('cdn_url', '')), '/');
    }

    public function pathPrefix(): string
    {
        return trim($this->string('path_prefix', 'catalog'), '/ ');
    }

    public function accessKey(): ?string
    {
        return $this->secrets->get(self::SECRET_ACCESS_KEY);
    }

    public function secretKey(): ?string
    {
        return $this->secrets->get(self::SECRET_SECRET_KEY);
    }

    public function apiToken(): ?string
    {
        return $this->secrets->get(self::SECRET_API_TOKEN);
    }

    public function setAccessKey(?string $value): void
    {
        $this->secrets->put(self::SECRET_ACCESS_KEY, $value !== null ? trim($value) : null);
    }

    public function setSecretKey(?string $value): void
    {
        $this->secrets->put(self::SECRET_SECRET_KEY, $value !== null ? trim($value) : null);
    }

    public function setApiToken(?string $value): void
    {
        $this->secrets->put(self::SECRET_API_TOKEN, $value !== null ? trim($value) : null);
    }

    public function hasAccessKey(): bool
    {
        return $this->secrets->has(self::SECRET_ACCESS_KEY);
    }

    public function hasSecretKey(): bool
    {
        return $this->secrets->has(self::SECRET_SECRET_KEY);
    }

    public function hasApiToken(): bool
    {
        return $this->secrets->has(self::SECRET_API_TOKEN);
    }

    public function accessKeyHint(): ?string
    {
        return $this->secrets->hint(self::SECRET_ACCESS_KEY);
    }

    public function secretKeyHint(): ?string
    {
        return $this->secrets->hint(self::SECRET_SECRET_KEY);
    }

    public function apiTokenHint(): ?string
    {
        return $this->secrets->hint(self::SECRET_API_TOKEN);
    }

    /**
     * Checks if the active provider has minimum required configuration to perform API uploads.
     */
    public function isConfigured(): bool
    {
        $provider = $this->provider();
        $bucket = $this->bucket();

        return match ($provider) {
            'cloudflare_api' => $bucket !== '' && $this->accountId() !== '' && ($this->hasApiToken() || $this->hasSecretKey()),
            'cloudflare_r2' => $bucket !== '' && ($this->accountId() !== '' || $this->endpoint() !== '') && $this->hasAccessKey() && $this->hasSecretKey(),
            'bunnycdn' => $bucket !== '' && ($this->hasApiToken() || $this->hasSecretKey() || $this->hasAccessKey()),
            'custom_api' => $this->endpoint() !== '',
            'minio', 'custom_s3' => $bucket !== '' && $this->endpoint() !== '' && $this->hasAccessKey() && $this->hasSecretKey(),
            default => $bucket !== '' && $this->hasAccessKey() && $this->hasSecretKey(),
        };
    }

    /**
     * Saves both manifest settings and optional secrets from the admin panel.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveConfiguration(array $data): void
    {
        $manifest = app(ModuleRepository::class)->find(ExternalStorageServiceProvider::MODULE)?->manifest();
        if ($manifest !== null) {
            $current = $this->schema->values($manifest);
            $merged = array_merge($current, array_intersect_key($data, $current));
            $this->schema->save($manifest, $merged);
        }

        if (array_key_exists('access_key', $data) && is_string($data['access_key']) && trim($data['access_key']) !== '') {
            $this->setAccessKey($data['access_key']);
        } elseif (($data['clear_access_key'] ?? false) === true) {
            $this->setAccessKey(null);
        }

        if (array_key_exists('secret_key', $data) && is_string($data['secret_key']) && trim($data['secret_key']) !== '') {
            $this->setSecretKey($data['secret_key']);
        } elseif (($data['clear_secret_key'] ?? false) === true) {
            $this->setSecretKey(null);
        }

        if (array_key_exists('api_token', $data) && is_string($data['api_token']) && trim($data['api_token']) !== '') {
            $this->setApiToken($data['api_token']);
        } elseif (($data['clear_api_token'] ?? false) === true) {
            $this->setApiToken(null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminProps(): array
    {
        return [
            'enabled' => $this->enabled(),
            'provider' => $this->provider(),
            'providerLabel' => $this->providerLabel(),
            'save_mode' => $this->saveMode(),
            'auto_offload_photos' => $this->autoOffloadPhotos(),
            'auto_offload_videos' => $this->autoOffloadVideos(),
            'delete_local_after_sync' => $this->deleteLocalAfterSync(),
            'show_product_videos' => $this->showProductVideos(),
            'bucket' => $this->bucket(),
            'region' => $this->region(),
            'account_id' => $this->accountId(),
            'endpoint' => $this->endpoint(),
            'cdn_url' => $this->cdnUrl(),
            'path_prefix' => $this->pathPrefix(),
            'isConfigured' => $this->isConfigured(),
            'hasAccessKey' => $this->hasAccessKey(),
            'hasSecretKey' => $this->hasSecretKey(),
            'hasApiToken' => $this->hasApiToken(),
            'accessKeyHint' => $this->accessKeyHint(),
            'secretKeyHint' => $this->secretKeyHint(),
            'apiTokenHint' => $this->apiTokenHint(),
        ];
    }

    private function bool(string $key, bool $default): bool
    {
        return (bool) $this->schema->of(ExternalStorageServiceProvider::MODULE, $key, $default);
    }

    private function string(string $key, string $default): string
    {
        $val = $this->schema->of(ExternalStorageServiceProvider::MODULE, $key, $default);

        return is_string($val) ? $val : $default;
    }
}
