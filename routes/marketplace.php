<?php

/*
| Marketplace server routes (ADR-033). They answer only on the platform
| owner's marketplace instance (ECOMODULAR_MARKETPLACE_SERVER=true) and 404
| on every merchant store.
*/

use App\Http\Controllers\Marketplace\LicenseApiController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\MarketplaceWebhookController;
use App\Http\Controllers\Marketplace\VendorController;
use App\Http\Middleware\ShareStorefrontData;
use Illuminate\Support\Facades\Route;

// Storefront and customer portal.
Route::middleware(['web', 'installed', 'marketplace.server', ShareStorefrontData::class])->group(function () {
    Route::get('marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('marketplace/products/{product}', [MarketplaceController::class, 'show'])->name('marketplace.product');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('marketplace/products/{product}/buy', [MarketplaceController::class, 'buy'])->middleware('throttle:10,1')->name('marketplace.buy');
        Route::get('marketplace/orders/{order}', [MarketplaceController::class, 'order'])->name('marketplace.orders.show');
        Route::get('account/marketplace', [MarketplaceController::class, 'portal'])->name('marketplace.portal');
        Route::post('account/marketplace/licenses/{license}/download', [MarketplaceController::class, 'download'])->middleware('throttle:20,1')->name('marketplace.portal.download');
        Route::delete('account/marketplace/licenses/{license}/activations/{activation}', [MarketplaceController::class, 'deactivate'])->name('marketplace.portal.deactivate');

        // Seller portal (Phase 8.4).
        Route::get('vendor', [VendorController::class, 'index'])->name('vendor.index');
        Route::post('vendor/apply', [VendorController::class, 'apply'])->middleware('throttle:5,1')->name('vendor.apply');
        Route::get('vendor/products/create', [VendorController::class, 'create'])->name('vendor.products.create');
        Route::post('vendor/products', [VendorController::class, 'store'])->name('vendor.products.store');
        Route::get('vendor/products/{product}/edit', [VendorController::class, 'edit'])->name('vendor.products.edit');
        Route::put('vendor/products/{product}', [VendorController::class, 'update'])->name('vendor.products.update');
        Route::post('vendor/products/{product}/releases', [VendorController::class, 'upload'])->middleware('throttle:10,1')->name('vendor.releases.store');
    });
});

// Stateless API for merchant stores: no session, no cookies, no CSRF.
Route::middleware(['installed', 'marketplace.server', 'throttle:marketplace-api'])
    ->prefix('api/marketplace/v1')
    ->name('marketplace.api.')
    ->group(function () {
        Route::get('catalog', [LicenseApiController::class, 'catalog'])->name('catalog');
        Route::post('licenses/activate', [LicenseApiController::class, 'activate'])->name('activate');
        Route::post('licenses/deactivate', [LicenseApiController::class, 'deactivate'])->name('deactivate');
        Route::post('licenses/check', [LicenseApiController::class, 'check'])->name('check');
        Route::post('updates', [LicenseApiController::class, 'update'])->name('updates');
        Route::post('downloads', [LicenseApiController::class, 'download'])->name('downloads');
    });

// Short-lived, single-use package downloads.
Route::get('marketplace/download/{token}', [LicenseApiController::class, 'file'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware(['installed', 'marketplace.server', 'throttle:marketplace-api'])
    ->name('marketplace.download');

Route::post('webhooks/marketplace/stripe', MarketplaceWebhookController::class)
    ->middleware(['installed', 'marketplace.server', 'throttle:webhooks'])
    ->name('webhooks.marketplace.stripe');
