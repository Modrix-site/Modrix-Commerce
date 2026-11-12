<?php

namespace EcoModular\CloudConnector;

use App\Core\Marketplace\Signing;
use App\Core\Marketplace\TrustedKeys;
use App\Core\Platform;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to Modrix Cloud from a merchant store. Nothing in the storefront or
 * checkout uses this class: an outage only affects the Admin → Modrix Cloud
 * page and downloads (ADR-035).
 *
 * License answers are signed tokens; they are only accepted when the
 * signature verifies against a trusted key (configuration only, never an
 * admin setting) and the token is for this store's instance.
 *
 * When the store is connected to an account, every call carries the
 * connection token, kept encrypted and never sent to the browser.
 */
final class CloudClient
{
    public const INSTANCE_KEY = 'marketplace.instance_id';

    public const MAX_DOWNLOAD_BYTES = 104_857_600;

    /** Encrypted connection token (SecretSettings). */
    public const TOKEN_KEY = 'cloud.connection_token';

    /** The connected account as last reported by Modrix Cloud: name, email, store name. */
    public const ACCOUNT_KEY = 'cloud.account';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    /** The official Modrix Cloud, so a fresh store connects without any configuration. */
    public const DEFAULT_URL = 'https://cloud-apps.modrixcommerce.com';

    /**
     * The official Modrix Cloud's Ed25519 public key (key id e6064ae59fe8ad04),
     * which signs every license and release it serves. Hardcoded so a store
     * connects without any configuration; must equal TrustedKeys::OFFICIAL.
     */
    public const TRUSTED_KEY = 'hfNCkhP3lhEjNPjg6Sd5D5s187ebC5ButhVJe+ilKKk=';

    /**
     * Modrix Cloud's address. Merchants cannot change it; ECOMODULAR_MARKETPLACE_URL
     * remains only so developers can point a store at a local cloud.
     */
    public function baseUrl(): string
    {
        $url = (string) config('ecomodular.marketplace.url');

        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/');
    }

    public function connected(): bool
    {
        return $this->secrets->get(self::TOKEN_KEY) !== null;
    }

    /**
     * @return array{name?: string, email?: string, store_name?: string, connected_at?: string}|null
     */
    public function account(): ?array
    {
        $account = $this->settings->get(self::ACCOUNT_KEY);

        return $this->connected() && is_array($account) ? $account : null;
    }

    /**
     * Where the browser goes to approve this store (PKCE S256).
     */
    public function authorizeUrl(string $state, string $verifier, string $returnUrl, string $storeName): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $url = parse_url($returnUrl);

        return $this->baseUrl().'/connect?'.http_build_query([
            'instance_id' => $this->instanceId(),
            'store_name' => mb_substr($storeName, 0, 160),
            'domain' => ($url['host'] ?? '').(isset($url['port']) ? ':'.$url['port'] : ''),
            'return_url' => $returnUrl,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Exchanges the one-time code for the connection token and stores it.
     *
     * @throws CloudError
     */
    public function connect(string $code, string $verifier): void
    {
        $body = $this->call('post', 'connections/token', ['code' => $code, 'code_verifier' => $verifier, 'instance_id' => $this->instanceId()]);
        $token = $body['token'] ?? null;

        if (! is_string($token) || strlen($token) !== 64) {
            throw new CloudError('Modrix Cloud did not return a connection. Try connecting again.');
        }

        $this->secrets->put(self::TOKEN_KEY, $token);
        $this->settings->set(self::ACCOUNT_KEY, $this->accountFields($body['account'] ?? []));
        $this->forgetCatalog();
    }

    /**
     * Forgets the connection here, and revokes it on Modrix Cloud when reachable.
     */
    public function disconnect(): void
    {
        if ($this->connected()) {
            try {
                $this->call('delete', 'connections/current');
            } catch (CloudError) {
                // Already revoked or unreachable: the token is forgotten here either way.
            }
        }

        $this->forget();
    }

    /**
     * Refreshes the account details (and confirms the connection still exists).
     *
     * @throws CloudError
     */
    public function refreshAccount(): void
    {
        $this->settings->set(self::ACCOUNT_KEY, $this->accountFields($this->call('get', 'connections/me')['account'] ?? []));
    }

    /**
     * The connected account's licenses, with keys.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CloudError
     */
    public function accountLicenses(): array
    {
        return array_values(array_filter((array) ($this->call('get', 'connections/licenses')['licenses'] ?? []), 'is_array'));
    }

    /**
     * The account's license for a package (created for a free package).
     *
     * @return array<string, mixed>
     *
     * @throws CloudError
     */
    public function claim(string $identifier): array
    {
        $license = $this->call('post', 'connections/licenses/claim', ['identifier' => $identifier])['license'] ?? null;

        return is_array($license) && is_string($license['key'] ?? null) ? $license : throw new CloudError('Modrix Cloud did not return a license for this package.');
    }

    /**
     * "Move here": frees the account's license from another store.
     *
     * @throws CloudError
     */
    public function release(string $identifier): void
    {
        $this->call('post', 'connections/licenses/release', ['identifier' => $identifier]);
    }

    public function forgetCatalog(): void
    {
        Cache::forget($this->catalogKey(true));
        Cache::forget($this->catalogKey(false));
    }

    private function forget(): void
    {
        $this->secrets->put(self::TOKEN_KEY, null);
        $this->settings->set(self::ACCOUNT_KEY, null);
        $this->forgetCatalog();
    }

    private function catalogKey(bool $connected): string
    {
        return 'modrix.cloud.catalog.'.($connected ? 'account' : 'public');
    }

    /**
     * @return array<string, string>
     */
    private function accountFields(mixed $account): array
    {
        $account = is_array($account) ? $account : [];

        return array_filter([
            'name' => is_string($account['name'] ?? null) ? $account['name'] : null,
            'email' => is_string($account['email'] ?? null) ? $account['email'] : null,
            'store_name' => is_string($account['store_name'] ?? null) ? $account['store_name'] : null,
            'connected_at' => is_string($account['connected_at'] ?? null) ? $account['connected_at'] : null,
        ]);
    }

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
     * @throws CloudError
     */
    public function catalog(): array
    {
        /** @var list<array<string, mixed>> */
        // Per-account flags (owned, license) only with a connection; cached briefly either way.
        return Cache::remember($this->catalogKey($this->connected()), 60, fn () => array_values(array_filter((array) ($this->call('get', 'catalog')['products'] ?? []), 'is_array')));
    }

    /**
     * @return array{token: array<string, mixed>, signature: string}
     *
     * @throws CloudError
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
     * @throws CloudError
     */
    public function check(string $key): array
    {
        return $this->verifiedToken($this->call('post', 'licenses/check', ['key' => $key, 'instance_id' => $this->instanceId()]));
    }

    /**
     * @throws CloudError
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
     * @throws CloudError
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
     * @throws CloudError
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
     * @throws CloudError
     */
    public function download(string $url, string $target, int $expectedSize): void
    {
        $base = $this->baseUrl();

        if ($base === '' || ! str_starts_with($url, $base.'/')) {
            throw new CloudError('The download link does not point to the configured Modrix Cloud address.');
        }

        if ($expectedSize <= 0 || $expectedSize > self::MAX_DOWNLOAD_BYTES) {
            throw new CloudError('The package size is not acceptable.');
        }

        try {
            $response = Http::timeout(120)->withOptions(['stream' => true])->get($url);
        } catch (ConnectionException) {
            throw new CloudError('Modrix Cloud could not be reached. Try again later.', unavailable: true);
        }

        if (! $response->successful()) {
            throw new CloudError((string) ($response->json('message') ?? 'The download was refused.'), $response->serverError(), $response->json('error'));
        }

        // Stream to disk, stopping as soon as the signed size is exceeded.
        $body = $response->toPsrResponse()->getBody();
        $out = fopen($target, 'wb');
        $written = 0;

        if ($out === false) {
            throw new CloudError('Could not write the downloaded package.');
        }

        while (! $body->eof()) {
            $chunk = $body->read(1_048_576);
            $written += strlen($chunk);

            if ($written > $expectedSize) {
                fclose($out);
                @unlink($target);
                throw new CloudError('The downloaded package is larger than its signed size.');
            }

            fwrite($out, $chunk);
        }

        fclose($out);

        if ($written !== $expectedSize) {
            @unlink($target);
            throw new CloudError('The downloaded package does not have the signed size.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{token: array<string, mixed>, signature: string}
     *
     * @throws CloudError
     */
    private function verifiedToken(array $response): array
    {
        $token = $response['token'] ?? null;
        $signature = $response['signature'] ?? null;

        if (! is_array($token) || ! is_string($signature) || ! Signing::verify($token, $signature, $this->trustedKeys())) {
            throw new CloudError('The Modrix Cloud answer could not be verified. Check ECOMODULAR_MARKETPLACE_TRUSTED_KEYS.');
        }

        if (($token['instance_id'] ?? null) !== $this->instanceId()) {
            throw new CloudError('The license answer is for another installation.');
        }

        return ['token' => $token, 'signature' => $signature];
    }

    /**
     * @return list<string>
     */
    public function trustedKeys(): array
    {
        return array_values(array_unique([self::TRUSTED_KEY, ...TrustedKeys::all()]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CloudError
     */
    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException) {
            throw new CloudError('Modrix Cloud could not be reached. Your store keeps working; try again later.', unavailable: true);
        }

        if ($response->serverError()) {
            throw new CloudError('Modrix Cloud is temporarily unavailable. Your store keeps working; try again later.', unavailable: true);
        }

        $body = $response->json();

        // The account or Modrix Cloud revoked this store's connection: forget it here too.
        if ($response->status() === 401 && is_array($body) && ($body['error'] ?? null) === 'not_connected') {
            $this->forget();
        }

        if (! $response->successful()) {
            throw new CloudError(is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : 'Modrix Cloud refused the request.', false, is_array($body) && is_string($body['error'] ?? null) ? $body['error'] : null);
        }

        return is_array($body) ? $body : throw new CloudError('Modrix Cloud sent an unreadable answer.', unavailable: true);
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl().'/api/marketplace/v1')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(max(2, (int) config('ecomodular.marketplace.timeout', 10)));
        $token = $this->secrets->get(self::TOKEN_KEY);

        return $token === null ? $request : $request->withToken($token);
    }
}
