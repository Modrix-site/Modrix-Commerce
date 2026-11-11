<?php

namespace EcoModular\ExternalStorage\Services;

use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Multi-cloud external storage HTTP/API client supporting:
 * - Wasabi Hot Cloud Storage (AWS Signature V4)
 * - Amazon S3 (AWS Signature V4)
 * - Cloudflare R2 via S3 API & Cloudflare Account REST API v4
 * - DigitalOcean Spaces (AWS Signature V4)
 * - Backblaze B2 Cloud Storage (AWS Signature V4)
 * - BunnyCDN Edge Storage HTTP API
 * - Google Cloud Storage (S3 Interoperable)
 * - MinIO / Custom S3 / Custom HTTP REST API
 */
class ExternalStorageClient
{
    public function __construct(
        private readonly ExternalStorageSettings $settings,
    ) {}

    /**
     * Uploads raw binary content to the configured external storage provider.
     *
     * @return array{remote_path: string, remote_url: string, provider: string, bucket: string}
     */
    public function putObject(string $path, string $contents, string $mimeType = 'application/octet-stream'): array
    {
        $remotePath = $this->normalizeRemotePath($path);
        $provider = $this->settings->provider();
        $bucket = $this->settings->bucket();

        $response = match ($provider) {
            'cloudflare_api' => $this->cloudflareApiPut($remotePath, $contents, $mimeType),
            'bunnycdn' => $this->bunnyCdnPut($remotePath, $contents, $mimeType),
            'custom_api' => $this->customApiPut($remotePath, $contents, $mimeType),
            default => $this->s3Request('PUT', $remotePath, $contents, $mimeType),
        };

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'External storage upload failed (%s HTTP %d): %s',
                $this->settings->providerLabel($provider),
                $response->status(),
                mb_substr($response->body(), 0, 240)
            ));
        }

        return [
            'remote_path' => $remotePath,
            'remote_url' => $this->publicUrl($remotePath),
            'provider' => $provider,
            'bucket' => $bucket,
        ];
    }

    /**
     * Downloads raw binary content from the external storage provider.
     */
    public function getObject(string $path): string
    {
        $remotePath = $this->normalizeRemotePath($path);
        $provider = $this->settings->provider();

        $response = match ($provider) {
            'cloudflare_api' => $this->cloudflareApiRequest('GET', $remotePath),
            'bunnycdn' => $this->bunnyCdnRequest('GET', $remotePath),
            'custom_api' => $this->customApiRequest('GET', $remotePath),
            default => $this->s3Request('GET', $remotePath),
        };

        if (! $response->successful()) {
            throw new RuntimeException("Unable to read remote object [{$remotePath}] (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /**
     * Checks if an object exists on the external storage provider.
     */
    public function objectExists(string $path): bool
    {
        try {
            $remotePath = $this->normalizeRemotePath($path);
            $provider = $this->settings->provider();

            $response = match ($provider) {
                'cloudflare_api' => $this->cloudflareApiRequest('HEAD', $remotePath),
                'bunnycdn' => $this->bunnyCdnRequest('HEAD', $remotePath),
                'custom_api' => $this->customApiRequest('HEAD', $remotePath),
                default => $this->s3Request('HEAD', $remotePath),
            };

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Deletes an object from the external storage provider.
     */
    public function deleteObject(string $path): bool
    {
        try {
            $remotePath = $this->normalizeRemotePath($path);
            $provider = $this->settings->provider();

            $response = match ($provider) {
                'cloudflare_api' => $this->cloudflareApiRequest('DELETE', $remotePath),
                'bunnycdn' => $this->bunnyCdnRequest('DELETE', $remotePath),
                'custom_api' => $this->customApiRequest('DELETE', $remotePath),
                default => $this->s3Request('DELETE', $remotePath),
            };

            return $response->successful() || $response->status() === 404;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Tests the connection & credentials against the active provider.
     *
     * @return array{ok: bool, message: string, provider: string, endpoint: string}
     */
    public function testConnection(): array
    {
        $provider = $this->settings->provider();
        $label = $this->settings->providerLabel($provider);

        if (! $this->settings->isConfigured()) {
            return [
                'ok' => false,
                'message' => "Missing required credentials or bucket configuration for {$label}.",
                'provider' => $provider,
                'endpoint' => $this->resolveBaseEndpoint(),
            ];
        }

        $probePath = $this->normalizeRemotePath('.healthcheck/ecomodular-connection-test.txt');
        $probeBody = 'EcoModular External Storage Connection Probe @ '.now()->toIso8601String();

        try {
            $this->putObject($probePath, $probeBody, 'text/plain');
            $this->deleteObject($probePath);

            return [
                'ok' => true,
                'message' => "Successfully connected, uploaded, and verified write access on {$label}.",
                'provider' => $provider,
                'endpoint' => $this->resolveBaseEndpoint(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'provider' => $provider,
                'endpoint' => $this->resolveBaseEndpoint(),
            ];
        }
    }

    /**
     * Normalizes a relative path and prepends the configured path_prefix if not already present.
     */
    public function normalizeRemotePath(string $path): string
    {
        $clean = ltrim(str_replace('\\', '/', trim($path)), '/');
        $prefix = $this->settings->pathPrefix();

        if ($prefix !== '' && $clean !== '' && ! str_starts_with($clean, $prefix.'/') && $clean !== $prefix) {
            return $prefix.'/'.$clean;
        }

        return $clean;
    }

    /**
     * Resolves the public/CDN URL for a given path.
     */
    public function publicUrl(string $path): string
    {
        $remotePath = $this->normalizeRemotePath($path);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $remotePath)));

        $cdnUrl = $this->settings->cdnUrl();
        if ($cdnUrl !== '') {
            return rtrim($cdnUrl, '/').'/'.$encodedPath;
        }

        $provider = $this->settings->provider();
        $bucket = $this->settings->bucket();
        $region = $this->settings->region();
        $accountId = $this->settings->accountId();
        $endpoint = $this->settings->endpoint();

        if ($endpoint !== '') {
            if (in_array($provider, ['custom_api', 'bunnycdn'], true)) {
                return rtrim($endpoint, '/').'/'.$encodedPath;
            }

            return rtrim($endpoint, '/').'/'.rawurlencode($bucket).'/'.$encodedPath;
        }

        return match ($provider) {
            'wasabi' => $region === 'us-east-1'
                ? "https://s3.wasabisys.com/{$bucket}/{$encodedPath}"
                : "https://s3.{$region}.wasabisys.com/{$bucket}/{$encodedPath}",
            's3' => "https://{$bucket}.s3.{$region}.amazonaws.com/{$encodedPath}",
            'cloudflare_r2', 'cloudflare_api' => "https://{$bucket}.{$accountId}.r2.cloudflarestorage.com/{$encodedPath}",
            'digitalocean' => "https://{$bucket}.{$region}.digitaloceanspaces.com/{$encodedPath}",
            'backblaze' => "https://{$bucket}.s3.{$region}.backblazeb2.com/{$encodedPath}",
            'bunnycdn' => "https://{$bucket}.b-cdn.net/{$encodedPath}",
            'gcs' => "https://storage.googleapis.com/{$bucket}/{$encodedPath}",
            default => "https://s3.{$region}.amazonaws.com/{$bucket}/{$encodedPath}",
        };
    }

    /**
     * Resolves the base API endpoint host & scheme for S3 or HTTP requests.
     */
    public function resolveBaseEndpoint(): string
    {
        $custom = $this->settings->endpoint();
        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        $provider = $this->settings->provider();
        $region = $this->settings->region();
        $accountId = $this->settings->accountId();

        return match ($provider) {
            'wasabi' => $region === 'us-east-1'
                ? 'https://s3.wasabisys.com'
                : "https://s3.{$region}.wasabisys.com",
            's3' => "https://s3.{$region}.amazonaws.com",
            'cloudflare_r2' => "https://{$accountId}.r2.cloudflarestorage.com",
            'cloudflare_api' => "https://api.cloudflare.com/client/v4/accounts/{$accountId}/r2/buckets/{$this->settings->bucket()}/objects",
            'digitalocean' => "https://{$region}.digitaloceanspaces.com",
            'backblaze' => "https://s3.{$region}.backblazeb2.com",
            'bunnycdn' => in_array($region, ['', 'de', 'us-east-1', 'Falkenstein'], true)
                ? 'https://storage.bunnycdn.com'
                : "https://{$region}.storage.bunnycdn.com",
            'gcs' => 'https://storage.googleapis.com',
            default => "https://s3.{$region}.amazonaws.com",
        };
    }

    /**
     * Executes an AWS Signature Version 4 signed request against any S3-compatible provider
     * (Wasabi, AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, GCS, MinIO, Custom S3).
     */
    private function s3Request(string $method, string $remotePath, string $body = '', ?string $mimeType = null): Response
    {
        $baseEndpoint = $this->resolveBaseEndpoint();
        $bucket = $this->settings->bucket();
        $provider = $this->settings->provider();
        $region = $provider === 'cloudflare_r2' ? 'auto' : $this->settings->region();

        $accessKey = (string) ($this->settings->accessKey() ?? '');
        $secretKey = (string) ($this->settings->secretKey() ?? '');

        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($remotePath, '/'))));
        $url = rtrim($baseEndpoint, '/').'/'.rawurlencode($bucket).'/'.$encodedKey;

        $parsed = parse_url($url);
        $host = ($parsed['host'] ?? 'localhost').(isset($parsed['port']) ? ':'.$parsed['port'] : '');
        $canonicalUri = $parsed['path'] ?? '/';

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];

        if ($method === 'PUT' && $mimeType !== null) {
            $headers['content-type'] = $mimeType;
            // Set public-read ACL on providers that use standard S3 object ACLs
            if (in_array($provider, ['wasabi', 'digitalocean', 'custom_s3'], true)) {
                $headers['x-amz-acl'] = 'public-read';
            }
        }

        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k).':'.trim($v)."\n";
        }

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $canonicalUri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.$secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $requestHeaders = [
            'Authorization' => $authorization,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ];

        if (isset($headers['x-amz-acl'])) {
            $requestHeaders['x-amz-acl'] = $headers['x-amz-acl'];
        }

        $pending = Http::withHeaders($requestHeaders)->timeout(60);

        if ($method === 'PUT') {
            return $pending->withBody($body, $mimeType ?? 'application/octet-stream')->send('PUT', $url);
        }

        return $pending->send($method, $url);
    }

    /**
     * Uploads an object via Cloudflare Account v4 R2 REST API.
     */
    private function cloudflareApiPut(string $remotePath, string $contents, string $mimeType): Response
    {
        return $this->cloudflareApiRequest('PUT', $remotePath, $contents, $mimeType);
    }

    /**
     * Executes a request against Cloudflare Account v4 R2 REST API.
     */
    private function cloudflareApiRequest(string $method, string $remotePath, string $body = '', ?string $mimeType = null): Response
    {
        $accountId = $this->settings->accountId();
        $bucket = $this->settings->bucket();
        $token = (string) ($this->settings->apiToken() ?? $this->settings->secretKey() ?? '');
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($remotePath, '/'))));

        $baseUrl = $this->settings->endpoint() !== ''
            ? rtrim($this->settings->endpoint(), '/')
            : "https://api.cloudflare.com/client/v4/accounts/{$accountId}/r2/buckets/{$bucket}/objects";

        $url = "{$baseUrl}/{$encodedKey}";

        $pending = Http::withToken($token)->timeout(60);

        if ($method === 'PUT') {
            return $pending->withBody($body, $mimeType ?? 'application/octet-stream')->send('PUT', $url);
        }

        return $pending->send($method, $url);
    }

    /**
     * Uploads an object via BunnyCDN Edge Storage HTTP API.
     */
    private function bunnyCdnPut(string $remotePath, string $contents, string $mimeType): Response
    {
        return $this->bunnyCdnRequest('PUT', $remotePath, $contents, $mimeType);
    }

    /**
     * Executes a request against BunnyCDN Edge Storage HTTP API.
     */
    private function bunnyCdnRequest(string $method, string $remotePath, string $body = '', ?string $mimeType = null): Response
    {
        $baseEndpoint = $this->resolveBaseEndpoint();
        $bucket = $this->settings->bucket();
        $accessKey = (string) ($this->settings->apiToken() ?? $this->settings->secretKey() ?? $this->settings->accessKey() ?? '');
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($remotePath, '/'))));

        $url = rtrim($baseEndpoint, '/').'/'.rawurlencode($bucket).'/'.$encodedKey;

        $pending = Http::withHeaders([
            'AccessKey' => $accessKey,
        ])->timeout(60);

        if ($method === 'PUT') {
            return $pending->withBody($body, $mimeType ?? 'application/octet-stream')->send('PUT', $url);
        }

        return $pending->send($method, $url);
    }

    /**
     * Uploads an object via Custom HTTP REST Storage API.
     */
    private function customApiPut(string $remotePath, string $contents, string $mimeType): Response
    {
        return $this->customApiRequest('PUT', $remotePath, $contents, $mimeType);
    }

    /**
     * Executes a request against a Custom HTTP REST Storage API.
     */
    private function customApiRequest(string $method, string $remotePath, string $body = '', ?string $mimeType = null): Response
    {
        $endpoint = $this->resolveBaseEndpoint();
        $bucket = $this->settings->bucket();
        $token = (string) ($this->settings->apiToken() ?? $this->settings->secretKey() ?? '');
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($remotePath, '/'))));

        $url = rtrim($endpoint, '/').'/'.$encodedKey;

        $headers = [];
        if ($bucket !== '') {
            $headers['X-Storage-Bucket'] = $bucket;
        }
        if ($this->settings->accessKey() !== null) {
            $headers['X-Access-Key'] = (string) $this->settings->accessKey();
        }

        $pending = Http::withHeaders($headers)->timeout(60);
        if ($token !== '') {
            $pending = $pending->withToken($token);
        }

        if ($method === 'PUT') {
            return $pending->withBody($body, $mimeType ?? 'application/octet-stream')->send('PUT', $url);
        }

        return $pending->send($method, $url);
    }
}
