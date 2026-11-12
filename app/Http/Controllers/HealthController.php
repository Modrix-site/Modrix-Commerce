<?php

namespace App\Http\Controllers;

use App\Core\Operations\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Readiness endpoint for load balancers and uptime monitors. Anonymous
 * callers get only the overall status (503 when a check failed); the
 * per-check details need the ECOMODULAR_HEALTH_TOKEN bearer token.
 * `/up` remains the plain liveness probe.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): JsonResponse
    {
        $checks = $health->run();
        $overall = HealthCheck::overall($checks);
        $token = (string) config('ecomodular.health.token', '');
        $authorized = $token !== '' && hash_equals($token, (string) $request->bearerToken());

        return response()->json(
            $authorized ? ['status' => $overall, 'checks' => $checks] : ['status' => $overall],
            $overall === HealthCheck::FAILED ? 503 : 200,
            ['Cache-Control' => 'no-store'],
        );
    }
}
