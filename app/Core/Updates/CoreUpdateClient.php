<?php

namespace App\Core\Updates;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to Modrix Cloud about core platform releases. Shares the connection
 * token and instance id with the Cloud Connector (same settings keys).
 */
final class CoreUpdateClient
{
    private const DEFAULT_URL = 'https://cloud-apps.modrixcommerce.com';

    private const TOKEN_KEY = 'cloud.connection_token';

    private const INSTANCE_KEY = 'marketplace.instance_id';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function connected(): bool
    {
        return $this->secrets->get(self::TOKEN_KEY) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws CoreUpdateError
     */
    public function releases(string $current, string $php): array
    {
        $body = $this->call('get', 'core/releases', ['current' => $current, 'php' => $php]);

        return array_values(array_filter((array) ($body['releases'] ?? []), 'is_array'));
    }

    /**
     * @return array{url: string, expires_at?: string, release?: array<string, mixed>}
     *
     * @throws CoreUpdateError
     */
    public function requestDownload(int $releaseId): array
    {
        if (! $this->connected()) {
            throw new CoreUpdateError('Connect this store to Modrix Cloud (Admin > Modrix Cloud) to download platform updates.', notConnected: true);
        }

        $body = $this->call('post', 'core/downloads', ['release_id' => $releaseId, 'instance_id' => $this->instanceId()]);

        if (! is_string($body['url'] ?? null)) {
            throw new CoreUpdateError('Modrix Cloud did not return a download link.', unavailable: true);
        }

        /** @var array{url: string, expires_at?: string, release?: array<string, mixed>} */
        return $body;
    }

    /**
     * Streams a release package to a local file, refusing anything larger
     * than the signed size (or the configured cap).
     *
     * @throws CoreUpdateError
     */
    public function download(string $url, string $target, int $expectedSize): void
    {
        $base = $this->baseUrl();
        $cap = (int) config('ecomodular.updates.max_mb', 300) * 1024 * 1024;

        if (! str_starts_with($url, $base.'/')) {
            throw new CoreUpdateError('The download link does not point to the configured Modrix Cloud address.');
        }

        if ($expectedSize <= 0 || $expectedSize > $cap) {
            throw new CoreUpdateError('The package size is not acceptable.');
        }

        try {
            $response = Http::timeout(120)->withOptions(['stream' => true])->get($url);
        } catch (ConnectionException) {
            throw new CoreUpdateError('Modrix Cloud could not be reached. Try again later.', unavailable: true);
        }

        if (! $response->successful()) {
            throw new CoreUpdateError((string) ($response->json('message') ?? 'The download was refused.'), unavailable: $response->serverError());
        }

        $body = $response->toPsrResponse()->getBody();
        $out = fopen($target, 'wb');
        $written = 0;

        if ($out === false) {
            throw new CoreUpdateError('Could not write the downloaded package.');
        }

        while (! $body->eof()) {
            $chunk = $body->read(1_048_576);
            $written += strlen($chunk);

            if ($written > $expectedSize) {
                fclose($out);
                @unlink($target);
                throw new CoreUpdateError('The downloaded package is larger than its signed size.');
            }

            fwrite($out, $chunk);
        }

        fclose($out);

        if ($written !== $expectedSize) {
            @unlink($target);
            throw new CoreUpdateError('The downloaded package does not have the signed size.');
        }
    }

    private function baseUrl(): string
    {
        $url = (string) config('ecomodular.marketplace.url');

        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/');
    }

    /** Same key as the Cloud Connector, so both share one id. */
    private function instanceId(): string
    {
        $id = $this->settings->get(self::INSTANCE_KEY);

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            $this->settings->set(self::INSTANCE_KEY, $id);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CoreUpdateError
     */
    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException) {
            throw new CoreUpdateError('Modrix Cloud could not be reached. Your store keeps working; try again later.', unavailable: true);
        }

        if ($response->serverError()) {
            throw new CoreUpdateError('Modrix Cloud is temporarily unavailable. Your store keeps working; try again later.', unavailable: true);
        }

        $body = $response->json();

        if ($response->status() === 401 && is_array($body) && ($body['error'] ?? null) === 'not_connected') {
            throw new CoreUpdateError('This store is no longer connected to Modrix Cloud. Connect it again to download platform updates.', notConnected: true);
        }

        if (! $response->successful()) {
            throw new CoreUpdateError(is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : 'Modrix Cloud refused the request.');
        }

        return is_array($body) ? $body : throw new CoreUpdateError('Modrix Cloud sent an unreadable answer.', unavailable: true);
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
