<?php

namespace App\Core\Marketplace\Client;

use App\Core\Marketplace\Signing;
use App\Core\Platform;
use App\Core\Settings\SettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to the official marketplace from a merchant store. Nothing in the
 * storefront or checkout uses this class: a marketplace outage only affects
 * the Admin → Marketplace page and downloads (ADR-035).
 *
 * License answers are signed tokens; they are only accepted when the
 * signature verifies against a trusted marketplace key and the token is for
 * this store's instance.
 */
final class MarketplaceClient
{
    public const INSTANCE_KEY = 'marketplace.instance_id';

    public const MAX_DOWNLOAD_BYTES = 104_857_600;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * This installation's random identifier, created on first use.
     */
    public function instanceId(): string
    {
        $id = $this->settings->get(self::INSTANCE_KEY);

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            $this->settings->set(self::INSTANCE_KEY, $id);
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws MarketplaceError
     */
    public function catalog(): array
    {
        /** @var list<array<string, mixed>> */
        return Cache::remember('ecomodular.marketplace.catalog', 3600, fn () => array_values((array) ($this->call('get', 'catalog')['products'] ?? [])));
    }

    /**
     * @return array{token: array<string, mixed>, signature: string}
     *
     * @throws MarketplaceError
     */
    public function activate(string $key): array
    {
        return $this->verifiedToken($this->call('post', 'licenses/activate', [
            'key' => $key,
            'instance_id' => $this->instanceId(),
            'domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
            'platform_version' => Platform::VERSION,
        ]));
    }

    /**
     * @return array{token: array<string, mixed>, signature: string}
     *
     * @throws MarketplaceError
     */
    public function check(string $key): array
    {
        return $this->verifiedToken($this->call('post', 'licenses/check', ['key' => $key, 'instance_id' => $this->instanceId()]));
    }

    /**
     * @throws MarketplaceError
     */
    public function deactivate(string $key): void
    {
        $this->call('post', 'licenses/deactivate', ['key' => $key, 'instance_id' => $this->instanceId()]);
    }

    /**
     * Untrusted answer: callers check every field; release metadata is only
     * trusted after its signature verifies.
     *
     * @return array<string, mixed>
     *
     * @throws MarketplaceError
     */
    public function update(string $key, ?string $installedVersion): array
    {
        return $this->call('post', 'updates', [
            'key' => $key,
            'instance_id' => $this->instanceId(),
            'installed_version' => $installedVersion,
            'platform_version' => Platform::VERSION,
        ]);
    }

    /**
     * @return array{url: string, expires_at: string, release: array<string, mixed>}
     *
     * @throws MarketplaceError
     */
    public function requestDownload(string $key, int $releaseId): array
    {
        /** @var array{url: string, expires_at: string, release: array<string, mixed>} */
        return $this->call('post', 'downloads', ['key' => $key, 'instance_id' => $this->instanceId(), 'release_id' => $releaseId]);
    }

    /**
     * Downloads a package to a local file, refusing anything larger than the
     * signed size (or the global cap).
     *
     * @throws MarketplaceError
     */
    public function download(string $url, string $target, int $expectedSize): void
    {
        $base = config('ecomodular.marketplace.url');

        if (! is_string($base) || ! str_starts_with($url, $base.'/')) {
            throw new MarketplaceError('The download link does not point to the configured marketplace.');
        }

        if ($expectedSize <= 0 || $expectedSize > self::MAX_DOWNLOAD_BYTES) {
            throw new MarketplaceError('The package size is not acceptable.');
        }

        try {
            $response = Http::timeout(120)->withOptions(['stream' => true])->get($url);
        } catch (ConnectionException) {
            throw new MarketplaceError('The marketplace could not be reached. Try again later.', unavailable: true);
        }

        if (! $response->successful()) {
            throw new MarketplaceError((string) ($response->json('message') ?? 'The download was refused.'), $response->serverError(), $response->json('error'));
        }

        // Stream to disk, stopping as soon as the signed size is exceeded.
        $body = $response->toPsrResponse()->getBody();
        $out = fopen($target, 'wb');
        $written = 0;

        if ($out === false) {
            throw new MarketplaceError('Could not write the downloaded package.');
        }

        while (! $body->eof()) {
            $chunk = $body->read(1_048_576);
            $written += strlen($chunk);

            if ($written > $expectedSize) {
                fclose($out);
                @unlink($target);
                throw new MarketplaceError('The downloaded package is larger than its signed size.');
            }

            fwrite($out, $chunk);
        }

        fclose($out);

        if ($written !== $expectedSize) {
            @unlink($target);
            throw new MarketplaceError('The downloaded package does not have the signed size.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{token: array<string, mixed>, signature: string}
     *
     * @throws MarketplaceError
     */
    private function verifiedToken(array $response): array
    {
        $token = $response['token'] ?? null;
        $signature = $response['signature'] ?? null;

        if (! is_array($token) || ! is_string($signature) || ! Signing::verify($token, $signature, $this->trustedKeys())) {
            throw new MarketplaceError('The marketplace answer could not be verified. Check ECOMODULAR_MARKETPLACE_TRUSTED_KEYS.');
        }

        if (($token['instance_id'] ?? null) !== $this->instanceId()) {
            throw new MarketplaceError('The license answer is for another installation.');
        }

        return ['token' => $token, 'signature' => $signature];
    }

    /**
     * @return list<string>
     */
    public function trustedKeys(): array
    {
        return array_values(array_filter((array) config('ecomodular.marketplace.trusted_keys', []), 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws MarketplaceError
     */
    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException) {
            throw new MarketplaceError('The marketplace could not be reached. Your store keeps working; try again later.', unavailable: true);
        }

        if ($response->serverError()) {
            throw new MarketplaceError('The marketplace is temporarily unavailable. Your store keeps working; try again later.', unavailable: true);
        }

        $body = $response->json();

        if (! $response->successful()) {
            throw new MarketplaceError(is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : 'The marketplace refused the request.', false, is_array($body) && is_string($body['error'] ?? null) ? $body['error'] : null);
        }

        return is_array($body) ? $body : throw new MarketplaceError('The marketplace sent an unreadable answer.', unavailable: true);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('ecomodular.marketplace.url').'/api/marketplace/v1')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(max(2, (int) config('ecomodular.marketplace.timeout', 10)));
    }
}
