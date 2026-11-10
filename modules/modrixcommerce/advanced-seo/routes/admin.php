<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/advanced-seo with the
| admin middleware and the "admin.modrixcommerce.advanced-seo." name prefix.
*/

use EcoModular\AdvancedSeo\Http\SeoAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:seo.manage')->group(function () {
    Route::get('/', [SeoAdminController::class, 'index'])->name('index');
    Route::post('google/sitemap', [SeoAdminController::class, 'submitSitemap'])->middleware('throttle:6,1')->name('sitemap');
    Route::post('google/crawl', [SeoAdminController::class, 'requestCrawl'])->middleware('throttle:6,1')->name('crawl');
});
