<?php

namespace Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Marketplace\Models\License;
use Marketplace\Models\LicenseActivation;
use Marketplace\Services\Licensing;

/**
 * Store license API. Each license is active on one store installation
 * (instance_id) at a time; answers are signed tokens the store verifies.
 */
class LicenseController
{
    public function __construct(private readonly Licensing $licensing) {}

    public function activate(Request $request): JsonResponse
    {
        $data = ApiError::validate($request, [
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:128'],
            'domain' => ['nullable', 'string', 'max:255'],
            'platform_version' => ['nullable', 'string', 'max:32'],
        ]);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = DB::transaction(function () use ($data) {
            $license = License::query()->where('key', $data['key'])->lockForUpdate()->first();

            if (! $license instanceof License) {
                return ApiError::response(404, 'invalid_key', 'This license key is not valid.');
            }

            if ($license->status !== 'active') {
                return ApiError::response(403, 'license_inactive', 'This license is no longer active.');
            }

            $active = $license->activeActivation();

            if ($active instanceof LicenseActivation && $active->instance_id !== $data['instance_id']) {
                return ApiError::response(409, 'license_in_use', 'This license is already active on another store. Deactivate it there first, or release it from your marketplace account.');
            }

            LicenseActivation::query()->updateOrCreate(
                ['license_id' => $license->id, 'instance_id' => $data['instance_id']],
                ['domain' => $data['domain'] ?? null, 'active' => true, 'activated_at' => now(), 'deactivated_at' => null],
            );

            return $license;
        });

        return $result instanceof JsonResponse ? $result : response()->json($this->licensing->token($result, $data['instance_id']));
    }

    public function check(Request $request): JsonResponse
    {
        $data = ApiError::validate($request, [
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:128'],
        ]);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $license = License::query()->where('key', $data['key'])->first();

        if (! $license instanceof License) {
            return ApiError::response(404, 'invalid_key', 'This license key is not valid.');
        }

        // Inactive licenses still get a signed answer so the store can show why.
        return response()->json($this->licensing->token($license, $data['instance_id']));
    }

    public function deactivate(Request $request): JsonResponse
    {
        $data = ApiError::validate($request, [
            'key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:128'],
        ]);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $license = License::query()->where('key', $data['key'])->first();

        if (! $license instanceof License) {
            return ApiError::response(404, 'invalid_key', 'This license key is not valid.');
        }

        $license->activations()->where('instance_id', $data['instance_id'])->where('active', true)
            ->update(['active' => false, 'deactivated_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
