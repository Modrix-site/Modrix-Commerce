<?php

namespace Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The store API's error shape: `{error, message}` with a status code, which
 * the store platform's MarketplaceClient shows to the merchant.
 */
final class ApiError
{
    public static function response(int $status, string $error, string $message): JsonResponse
    {
        return response()->json(['error' => $error, 'message' => $message], $status);
    }

    /**
     * Validates the request; returns the data, or a 422 error response.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>|JsonResponse
     */
    public static function validate(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return self::response(422, 'validation_failed', $validator->errors()->first());
        }

        return $validator->validated();
    }
}
