<?php

namespace App\Http\Controllers\Marketplace;

use App\Domains\Marketplace\LicenseApi;
use App\Domains\Marketplace\LicenseApiException;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketplace server API used by merchant stores (stateless JSON).
 * License keys travel in the request body over HTTPS, never in URLs.
 */
class LicenseApiController extends Controller
{
    public function __construct(private readonly LicenseApi $api) {}

    public function catalog(): JsonResponse
    {
        $products = MarketplaceProduct::query()
            ->where('status', MarketplaceProduct::PUBLISHED)
            ->orderBy('name')
            ->get()
            ->map(function (MarketplaceProduct $p) {
                $release = $p->latestPublishedRelease();

                return $release === null ? null : [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'summary' => $p->summary,
                    'type' => $p->type,
                    'extension' => $p->extension_name,
                    'price' => $p->money(),
                    'free' => $p->isFree(),
                    'latest_version' => $release->version,
                    'platform_version' => $release->platform_version,
                    'url' => route('marketplace.product', $p->slug),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['products' => $products]);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $this->validated($request, ['domain' => ['nullable', 'string', 'max:191'], 'platform_version' => ['nullable', 'string', 'max:32']]);

        return $this->respond(fn () => $this->api->activate($data['key'], $data['instance_id'], $data['domain'] ?? null, $data['platform_version'] ?? null, $request->ip()));
    }

    public function deactivate(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return $this->respond(function () use ($data, $request) {
            $this->api->deactivate($data['key'], $data['instance_id'], $request->ip());

            return ['deactivated' => true];
        });
    }

    public function check(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return $this->respond(fn () => $this->api->check($data['key'], $data['instance_id']));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->validated($request, [
            'installed_version' => ['nullable', 'string', 'max:64'],
            'platform_version' => ['required', 'string', 'max:32'],
        ]);

        return $this->respond(fn () => $this->api->update($data['key'], $data['instance_id'], $data['installed_version'] ?? null, $data['platform_version']));
    }

    public function download(Request $request): JsonResponse
    {
        $data = $this->validated($request, ['release_id' => ['required', 'integer']]);

        return $this->respond(fn () => $this->api->requestDownload($data['key'], $data['instance_id'], (int) $data['release_id'], $request->ip()));
    }

    /**
     * Streams a package for a valid, unused, unexpired download token.
     */
    public function file(Request $request, string $token): StreamedResponse|JsonResponse
    {
        try {
            $release = $this->api->redeem($token, $request->ip());
        } catch (LicenseApiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], 403);
        }

        return $this->stream($release);
    }

    private function stream(MarketplaceRelease $release): StreamedResponse
    {
        $name = str_replace('/', '-', $release->product->extension_name)."-{$release->version}.zip";

        return Storage::disk('local')->download($release->archive_path, $name, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'X-Package-Sha256' => $release->sha256,
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{16,64}$/'],
            ...$extra,
        ]);
    }

    /**
     * @param  Closure(): array<string, mixed>  $call
     */
    private function respond(Closure $call): JsonResponse
    {
        try {
            return response()->json($call());
        } catch (LicenseApiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->errorCode === 'invalid_key' ? 404 : 403);
        }
    }
}
