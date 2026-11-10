<?php

namespace EcoModular\AdvancedSeo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Submits the sitemap and asks Google to recrawl URLs with a Search Console
 * service account. The private key never leaves this class.
 */
final class GoogleSearchConsole
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters https://www.googleapis.com/auth/indexing';

    public function submitSitemap(string $credentialsJson, string $siteUrl, string $sitemapUrl): void
    {
        $token = $this->token($credentialsJson);
        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/sitemaps/'.rawurlencode($sitemapUrl);
        $response = Http::withToken($token)->acceptJson()->timeout(20)->put($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->error($response->json('error.message') ?? $response->body()));
        }
    }

    /**
     * @param  list<string>  $urls
     * @return array{submitted: int, failed: int}
     */
    public function requestCrawl(string $credentialsJson, array $urls): array
    {
        $token = $this->token($credentialsJson);
        $submitted = 0;
        $failed = 0;

        foreach ($urls as $url) {
            $response = Http::withToken($token)->acceptJson()->timeout(20)->post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
                'url' => $url,
                'type' => 'URL_UPDATED',
            ]);

            if ($response->successful()) {
                $submitted++;
            } else {
                $failed++;
            }
        }

        if ($submitted === 0 && $failed > 0) {
            throw new RuntimeException('Google did not accept the crawl requests. Confirm the Indexing API is enabled and the service account owns the property.');
        }

        return ['submitted' => $submitted, 'failed' => $failed];
    }

    public function accountEmail(string $credentialsJson): ?string
    {
        $parsed = json_decode($credentialsJson, true);
        $email = is_array($parsed) ? ($parsed['client_email'] ?? null) : null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function token(string $credentialsJson): string
    {
        $credentials = json_decode($credentialsJson, true);

        if (! is_array($credentials) || ! is_string($credentials['client_email'] ?? null) || ! is_string($credentials['private_key'] ?? null)) {
            throw new RuntimeException('The Google service account JSON is missing client_email or private_key.');
        }

        $cacheKey = 'modrixcommerce.advanced-seo.google.'.sha1($credentials['client_email']);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $segments = [
            $this->encode(['alg' => 'RS256', 'typ' => 'JWT']),
            $this->encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]),
        ];
        $unsigned = implode('.', $segments);
        $signed = openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new RuntimeException('The Google service account private key could not be used.');
        }

        $assertion = $unsigned.'.'.$this->encode($signature, true);
        $response = Http::asForm()->acceptJson()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        $access = $response->json('access_token');

        if (! is_string($access) || $access === '') {
            throw new RuntimeException($this->error($response->json('error_description') ?? 'Google did not issue an access token.'));
        }

        Cache::put($cacheKey, $access, now()->addMinutes(50));

        return $access;
    }

    private function encode(array|string $value, bool $raw = false): string
    {
        $json = $raw ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode(is_string($json) ? $json : ''), '+/', '-_'), '=');
    }

    private function error(mixed $message): string
    {
        $text = trim(strip_tags((string) $message));

        return $text === '' ? 'Google rejected the request.' : mb_substr($text, 0, 180);
    }
}
