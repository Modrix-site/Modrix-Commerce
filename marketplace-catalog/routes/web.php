<?php

use Illuminate\Support\Facades\Route;
use Marketplace\Http\Controllers\AccountController;
use Marketplace\Http\Controllers\Admin\AccessController;
use Marketplace\Http\Controllers\Admin\CategoryController;
use Marketplace\Http\Controllers\Admin\RepositoryController;
use Marketplace\Http\Controllers\Admin\SalesController;
use Marketplace\Http\Controllers\Admin\SettingsController;
use Marketplace\Http\Controllers\Admin\SubmissionController;
use Marketplace\Http\Controllers\AuthController;
use Marketplace\Http\Controllers\CatalogController;
use Marketplace\Http\Controllers\CheckoutController;
use Marketplace\Http\Controllers\InstallController;
use Marketplace\Http\Controllers\PublishController;
use Marketplace\Http\Controllers\StripeWebhookController;
use Marketplace\Http\Middleware\EnsureAdmin;

// First-run setup; RequireInstallation closes it (404) once the app is installed.
Route::get('install', [InstallController::class, 'show'])->name('install');
Route::post('install/database', [InstallController::class, 'database'])->middleware('throttle:10,1');
Route::post('install/admin', [InstallController::class, 'admin'])->middleware('throttle:10,1');

// Public catalog. Addresses match the original catalog app.
Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('item/{slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('media/{slug}', [CatalogController::class, 'media'])->name('catalog.media');
Route::get('branding/logo', [CatalogController::class, 'logo'])->name('catalog.logo');
Route::get('download/{slug}', [CatalogController::class, 'download'])->middleware('throttle:30,1')->name('catalog.download');

// Payments. The webhook is excluded from CSRF (bootstrap/app.php); Stripe signs it.
Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Customer accounts.
Route::middleware('guest')->group(function () {
    Route::get('account/login', [AuthController::class, 'login'])->name('login');
    Route::post('account/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::get('account/register', [AuthController::class, 'register'])->name('register');
    Route::post('account/register', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::post('account/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('account', [AccountController::class, 'show'])->name('account');
    Route::post('account/licenses', [AccountController::class, 'license'])->middleware('throttle:20,1');
    Route::post('account/licenses/release', [AccountController::class, 'release'])->middleware('throttle:20,1');

    Route::post('buy/{package:slug}', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('orders/success', [CheckoutController::class, 'success'])->name('checkout.success');

    // Publishers.
    Route::get('submit', [PublishController::class, 'create'])->name('publish.create');
    Route::post('submit', [PublishController::class, 'store'])->middleware('throttle:10,1');
    Route::get('submit/update', [PublishController::class, 'edit'])->name('publish.update');
    Route::post('submit/update', [PublishController::class, 'update'])->middleware('throttle:10,1');
    Route::get('submissions', [PublishController::class, 'index'])->name('publish.index');
});

// Admin.
Route::prefix('admin')->group(function () {
    Route::get('setup', [AccessController::class, 'setup']);
    Route::post('setup', [AccessController::class, 'createAdmin'])->middleware('throttle:5,1');
    Route::get('login', [AccessController::class, 'login'])->name('admin.login');
    Route::post('login', [AccessController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::post('logout', [AccessController::class, 'logout'])->name('admin.logout');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [RepositoryController::class, 'overview'])->name('admin.overview');
        Route::get('repository', [RepositoryController::class, 'index'])->name('admin.repository');
        Route::get('repository/new', [RepositoryController::class, 'create']);
        Route::post('repository/new', [RepositoryController::class, 'store']);
        Route::get('repository/{slug}', [RepositoryController::class, 'edit']);
        Route::post('repository/{slug}', [RepositoryController::class, 'update']);
        Route::get('updates', [RepositoryController::class, 'updates'])->name('admin.updates');
        Route::get('catalog', [RepositoryController::class, 'pricing'])->name('admin.catalog');
        Route::post('catalog', [RepositoryController::class, 'savePricing']);

        Route::get('submissions', [SubmissionController::class, 'index'])->name('admin.submissions');
        Route::post('submissions', [SubmissionController::class, 'review']);
        Route::get('submissions/{submission}/download', [SubmissionController::class, 'download']);

        Route::get('categories', [CategoryController::class, 'index'])->name('admin.categories');
        Route::post('categories', [CategoryController::class, 'save']);

        Route::get('general', [SettingsController::class, 'show'])->name('admin.settings');
        Route::post('general', [SettingsController::class, 'saveGeneral']);
        Route::post('stripe', [SettingsController::class, 'saveStripe']);
        Route::post('smtp', [SettingsController::class, 'saveSmtp']);
        Route::post('smtp/test', [SettingsController::class, 'testSmtp'])->middleware('throttle:5,1');

        Route::get('licenses', [SalesController::class, 'licenses'])->name('admin.licenses');
        Route::post('licenses/{license}/release', [SalesController::class, 'release']);
        Route::post('licenses/{license}/revoke', [SalesController::class, 'revoke']);
        Route::get('orders', [SalesController::class, 'orders'])->name('admin.orders');
    });
});
