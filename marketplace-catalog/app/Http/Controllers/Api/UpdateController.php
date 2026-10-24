<?php

namespace Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marketplace\Models\License;
use Marketplace\Services\Releases;

/**
 * POST updates — the newest signed release this store may install.
 */
class UpdateController
{
    public function __invoke(Request $request, Releases $releases): JsonResponse
    {
        $data = ApiError::validate($request, [
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:128'],
            'installed_version' => ['nullable', 'string', 'max:32'],
            'platform_version' => ['nullable', 'string', 'max:32'],
        ]);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $license = License::query()->with('package')->where('key', $data['key'])->first();

        if (! $license instanceof License) {
            return ApiError::response(404, 'invalid_key', 'This license key is not valid.');
        }

        if ($license->status !== 'active') {
            return response()->json(['available' => false, 'reason' => 'This license is no longer active.', 'release' => null]);
        }

        if (! $license->activations()->where('instance_id', $data['instance_id'])->where('active', true)->exists()) {
            return response()->json(['available' => false, 'reason' => 'Activate this license on the store first.', 'release' => null]);
        }

        $release = $releases->newestFor($license->package, $data['installed_version'] ?? null, $data['platform_version'] ?? null);

        return response()->json($release === null
            ? ['available' => false, 'reason' => 'You have the latest version for this platform.', 'release' => null]
            : ['available' => true, 'release' => $releases->payload($release)]);
    }
}
