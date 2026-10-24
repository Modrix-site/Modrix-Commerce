<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every address of the original catalog app is a Laravel route; nothing falls through. */
class RoutesTest extends TestCase
{
    public function test_every_legacy_url_has_a_laravel_route()
    {
        $urls = [
            ['GET', '/'], ['GET', '/item/acme-reviews'], ['GET', '/media/acme-reviews'], ['GET', '/download/acme-reviews'], ['GET', '/branding/logo'],
            ['GET', '/account/login'], ['POST', '/account/login'], ['GET', '/account/register'], ['POST', '/account/register'], ['POST', '/account/logout'],
            ['GET', '/account'], ['POST', '/account/licenses'], ['POST', '/account/licenses/release'],
            ['POST', '/buy/acme-reviews'], ['GET', '/orders/success'], ['POST', '/stripe/webhook'],
            ['GET', '/submit'], ['POST', '/submit'], ['GET', '/submit/update'], ['POST', '/submit/update'], ['GET', '/submissions'],
            ['GET', '/admin'], ['GET', '/admin/setup'], ['POST', '/admin/setup'], ['GET', '/admin/login'], ['POST', '/admin/login'], ['POST', '/admin/logout'],
            ['GET', '/admin/repository'], ['GET', '/admin/repository/new'], ['POST', '/admin/repository/new'], ['GET', '/admin/repository/acme-reviews'], ['POST', '/admin/repository/acme-reviews'],
            ['GET', '/admin/submissions'], ['POST', '/admin/submissions'], ['GET', '/admin/submissions/1/download'], ['GET', '/admin/updates'],
            ['GET', '/admin/categories'], ['POST', '/admin/categories'], ['GET', '/admin/catalog'], ['POST', '/admin/catalog'],
            ['GET', '/admin/general?tab=payments'], ['POST', '/admin/general'], ['POST', '/admin/stripe'], ['POST', '/admin/smtp'], ['POST', '/admin/smtp/test'],
            ['GET', '/admin/licenses'], ['POST', '/admin/licenses/1/release'], ['POST', '/admin/licenses/1/revoke'], ['GET', '/admin/orders'],
            ['GET', '/api/marketplace/v1/catalog'], ['POST', '/api/marketplace/v1/licenses/activate'], ['POST', '/api/marketplace/v1/licenses/check'],
            ['POST', '/api/marketplace/v1/licenses/deactivate'], ['POST', '/api/marketplace/v1/updates'], ['POST', '/api/marketplace/v1/downloads'],
            ['GET', '/api/marketplace/v1/download/abc-123'], ['GET', '/up'],
        ];

        foreach ($urls as [$method, $url]) {
            $route = Route::getRoutes()->match(Request::create($url, $method));
            $this->assertFalse($route->isFallback, "{$method} {$url} has no Laravel route.");
        }

        $fallbacks = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->isFallback);
        $this->assertCount(0, $fallbacks, 'The legacy front controller fallback is still registered.');
    }
}
