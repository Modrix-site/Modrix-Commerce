<?php

/*
| Loaded by the platform under /admin/m/ecomodular/sitemap with the admin
| middleware and the "admin.ecomodular.sitemap." name prefix.
*/

use EcoModular\Sitemap\Http\SitemapAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:sitemap.manage')->group(function () {
    Route::get('/', [SitemapAdminController::class, 'index'])->name('index');
    Route::post('regenerate', [SitemapAdminController::class, 'regenerate'])->name('regenerate');
});
