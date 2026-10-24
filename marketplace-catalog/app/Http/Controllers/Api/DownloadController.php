<?php

namespace Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marketplace\Models\DownloadToken;
use Marketplace\Models\License;
use Marketplace\Models\PackageRelease;
use Marketplace\Services\Releases;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST downloads issues a one-time link (10 minutes) for a release of the
 * licensed package; GET download/{token} streams the ZIP once.
 */
class DownloadController
{
    public function __construct(private readonly Releases $releases) {}

    public function request(Request $request): JsonResponse
    {
        $data = ApiError::validate($request, [
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:128'],
            'release_id' => ['required', 'integer'],
        ]);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $license = License::query()->where('key', $data['key'])->first();

        if (! $license instanceof License) {
            return ApiError::response(404, 'invalid_key', 'This license key is not valid.');
        }

        if ($license->status !== 'active') {
            return ApiError::response(403, 'license_inactive', 'This license is no longer active.');
        }

        $release = PackageRelease::query()->whereKey((int) $data['release_id'])->where('package_id', $license->package_id)->first();

        if (! $release instanceof PackageRelease) {
            return ApiError::response(404, 'release_not_found', 'This release does not exist for your license.');
        }

        if (! $license->activations()->where('instance_id', $data['instance_id'])->where('active', true)->exists()) {
            return ApiError::response(403, 'not_activated', 'Activate this license on the store first.');
        }

        $token = Str::random(64);
        $expires = now()->addMinutes((int) config('marketplace.download_minutes', 10));
        DownloadToken::query()->create([
            'token_hash' => hash('sha256', $token),
            'license_id' => $license->id,
            'release_id' => $release->id,
            'instance_id' => $data['instance_id'],
            'expires_at' => $expires,
        ]);

        return response()->json([
            'url' => url('/api/marketplace/v1/download/'.$token),
            'expires_at' => $expires->toIso8601String(),
            'release' => $this->releases->payload($release),
        ]);
    }

    public function download(string $token): Response
    {
        // Claim the token atomically so it can only be used once. A missing
        // file or a license revoked since the link was made does not use it up.
        $result = DB::transaction(function () use ($token) {
            $row = DownloadToken::query()->with(['release.package', 'license'])->whereKey(hash('sha256', $token))->lockForUpdate()->first();

            if (! $row instanceof DownloadToken || $row->used_at !== null || $row->expires_at->isPast()) {
                return ApiError::response(410, 'link_expired', 'This download link has expired or was already used.');
            }

            if ($row->license?->status !== 'active') {
                return ApiError::response(403, 'license_inactive', 'This license is no longer active.');
            }

            if (! is_file($this->releases->path($row->release))) {
                return ApiError::response(404, 'release_not_found', 'The release file is not available right now. Try again later.');
            }

            $row->forceFill(['used_at' => now()])->save();

            return $row;
        });

        if (! $result instanceof DownloadToken) {
            return $result;
        }

        $name = $result->release->package->slug.'-'.$result->release->version.'.zip';

        return response()->download($this->releases->path($result->release), $name, ['Content-Type' => 'application/zip']);
    }
}
