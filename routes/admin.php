<?php

/*
| Admin routes. Registered in bootstrap/app.php under the "admin." name prefix
| and the web, installed, auth, verified and staff middleware.
| Every route below additionally declares the permission it requires.
| Mutations of users and roles require a recent password confirmation.
*/

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Catalog\AttributeController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductCsvController;
use App\Http\Controllers\Admin\Catalog\ProductImageController;
use App\Http\Controllers\Admin\Catalog\TranslationController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExtensionController;
use App\Http\Controllers\Admin\InterfaceTextController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LanguageSettingsController;
use App\Http\Controllers\Admin\MarketplaceAdminController;
use App\Http\Controllers\Admin\MarketplaceClientController;
use App\Http\Controllers\Admin\MarketplaceSellerController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\PaymentWebhookEventController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\Admin\StripeSettingsController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Marketplace\MarketplaceReviewController;
use Illuminate\Support\Facades\Route;

// Numeric ids only, so named paths such as extensions/marketplace are never captured.
Route::pattern('extension', '[0-9]+');

Route::get('/', DashboardController::class)->name('dashboard');

Route::middleware('can:users.view')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
});

Route::middleware(['can:users.manage', 'password.confirm'])->group(function () {
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
    Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status.update');
});

Route::middleware(['can:roles.manage', 'password.confirm'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware('can:settings.manage')->group(function () {
    Route::get('settings/store', [StoreSettingsController::class, 'edit'])->name('settings.store.edit');
    Route::put('settings/store', [StoreSettingsController::class, 'update'])->name('settings.store.update');
    Route::get('settings/languages', [LanguageSettingsController::class, 'edit'])->name('settings.languages.edit');
    Route::put('settings/languages', [LanguageSettingsController::class, 'update'])->name('settings.languages.update');
    Route::get('settings/languages/texts', [InterfaceTextController::class, 'index'])->name('settings.interface-text.index');
    Route::put('settings/languages/texts/{locale}', [InterfaceTextController::class, 'update'])->where('locale', '[a-z]{2}')->name('settings.interface-text.update');
});

// Media library (Admin → Catalog → Media library).
Route::middleware('can:media.manage')->group(function () {
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media')->name('media.show');
    Route::put('media/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');
});
Route::post('products/{product}/images/from-library', [MediaController::class, 'attach'])
    ->middleware(['can:products.update', 'can:media.manage'])
    ->name('products.images.from-library');

// Catalog
Route::middleware('can:products.view')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/export', [ProductCsvController::class, 'export'])->name('products.export');
});
Route::post('products/import', [ProductCsvController::class, 'import'])
    ->middleware(['can:products.create', 'can:products.update', 'throttle:10,1'])
    ->name('products.import');
Route::middleware('can:products.create')->group(function () {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
});
Route::middleware('can:products.update')->group(function () {
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
    Route::put('products/{product}/images/order', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
    Route::put('products/{product}/images/{image}', [ProductImageController::class, 'update'])->name('products.images.update')->scopeBindings();
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy')->scopeBindings();

    // Storefront translations.
    Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');
    Route::get('products/{product}/translations', [TranslationController::class, 'editProduct'])->name('products.translations.edit');
    Route::put('products/{product}/translations/{locale}', [TranslationController::class, 'updateProduct'])->where('locale', '[a-z]{2}')->name('products.translations.update');
    Route::get('categories/{category}/translations', [TranslationController::class, 'editCategory'])->name('categories.translations.edit');
    Route::put('categories/{category}/translations/{locale}', [TranslationController::class, 'updateCategory'])->where('locale', '[a-z]{2}')->name('categories.translations.update');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::get('attributes/create', [AttributeController::class, 'create'])->name('attributes.create');
    Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
    Route::get('attributes/{attribute}/edit', [AttributeController::class, 'edit'])->name('attributes.edit');
    Route::put('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');
});
Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('can:products.delete')->name('products.destroy');

// Inventory
Route::middleware('can:inventory.view')->group(function () {
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});
Route::post('inventory/{variant}/adjust', [InventoryController::class, 'adjust'])->middleware('can:inventory.manage')->name('inventory.adjust');

// Orders
Route::middleware('can:orders.view')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
Route::middleware('can:orders.manage')->group(function () {
    Route::post('orders/{order}/payments', [OrderController::class, 'recordPayment'])->name('orders.payments.store');
    Route::post('orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
    Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
    Route::post('orders/{order}/hold', [OrderController::class, 'hold'])->name('orders.hold');
    Route::post('orders/{order}/release', [OrderController::class, 'release'])->name('orders.release');
    Route::post('orders/{order}/pack', [OrderController::class, 'pack'])->name('orders.pack');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/notes', [OrderController::class, 'note'])->name('orders.notes.store');
});
Route::post('orders/{order}/refunds', [OrderController::class, 'refund'])->middleware('can:orders.refund')->name('orders.refunds.store');

// Customers
Route::middleware('can:customers.view')->group(function () {
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});
Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('can:customers.manage')->name('customers.update');

// Discounts
Route::get('coupons', [CouponController::class, 'index'])->middleware('can:discounts.view')->name('coupons.index');
Route::middleware('can:discounts.manage')->group(function () {
    Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
});

// Shipping, taxes, payments
Route::middleware('can:shipping.manage')->group(function () {
    Route::get('shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
    Route::post('shipping-methods', [ShippingMethodController::class, 'store'])->name('shipping-methods.store');
    Route::put('shipping-methods/{method}', [ShippingMethodController::class, 'update'])->name('shipping-methods.update');
    Route::delete('shipping-methods/{method}', [ShippingMethodController::class, 'destroy'])->name('shipping-methods.destroy');
});
Route::middleware('can:settings.manage')->group(function () {
    Route::get('tax-rates', [TaxController::class, 'index'])->name('tax-rates.index');
    Route::put('tax-rates/settings', [TaxController::class, 'updateSettings'])->name('tax-rates.settings');
    Route::post('tax-rates', [TaxController::class, 'store'])->name('tax-rates.store');
    Route::put('tax-rates/{rate}', [TaxController::class, 'update'])->name('tax-rates.update');
    Route::delete('tax-rates/{rate}', [TaxController::class, 'destroy'])->name('tax-rates.destroy');
});
Route::middleware('can:payments.manage')->group(function () {
    Route::get('payments', [PaymentSettingsController::class, 'edit'])->name('payments.settings');
    Route::put('payments', [PaymentSettingsController::class, 'update'])->name('payments.settings.update');
    Route::get('payments/webhooks', [PaymentWebhookEventController::class, 'index'])->name('payments.webhooks.index');
    Route::post('payments/webhooks/{event}/retry', [PaymentWebhookEventController::class, 'retry'])->middleware('throttle:20,1')->name('payments.webhooks.retry');
});
// Gateway credentials decide where customers' money goes.
Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('payments/stripe', [StripeSettingsController::class, 'edit'])->name('payments.stripe.edit');
    Route::put('payments/stripe', [StripeSettingsController::class, 'update'])->name('payments.stripe.update');
    Route::post('payments/stripe/test', [StripeSettingsController::class, 'test'])->middleware('throttle:10,1')->name('payments.stripe.test');
});

// Reports
Route::get('reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');

// Appearance (themes). Themes are data-only, so no password confirmation is needed.
Route::middleware('can:themes.manage')->group(function () {
    Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('themes/preview/exit', [ThemeController::class, 'exitPreview'])->name('themes.preview.exit');
    Route::post('themes/uploads', [ThemeController::class, 'upload'])->middleware('throttle:30,1')->name('themes.uploads.store');
    Route::get('themes/{theme}/customize', [ThemeController::class, 'customize'])->name('themes.customize');
    Route::post('themes/{theme}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::post('themes/{theme}/preview', [ThemeController::class, 'preview'])->name('themes.preview');
    Route::post('themes/{theme}/update', [ThemeController::class, 'update'])->name('themes.update');
    Route::put('themes/{theme}/design', [ThemeController::class, 'design'])->name('themes.design');
    Route::put('themes/{theme}/sections', [ThemeController::class, 'sections'])->name('themes.sections');
    Route::post('themes/{theme}/reset', [ThemeController::class, 'reset'])->name('themes.reset');
    Route::post('themes/{theme}/demo', [ThemeController::class, 'demo'])->middleware('throttle:5,1')->name('themes.demo');
});

// Marketplace client (every store): licenses, installs and updates from the official marketplace.
Route::middleware('can:extensions.view')->group(function () {
    Route::get('extensions/marketplace', [MarketplaceClientController::class, 'index'])->name('marketplace-client.index');
});
Route::middleware(['can:licenses.manage', 'throttle:20,1'])->group(function () {
    Route::post('extensions/marketplace/licenses', [MarketplaceClientController::class, 'activate'])->name('marketplace-client.licenses.activate');
    Route::post('extensions/marketplace/licenses/{license}/check', [MarketplaceClientController::class, 'check'])->name('marketplace-client.licenses.check');
    Route::delete('extensions/marketplace/licenses/{license}', [MarketplaceClientController::class, 'deactivate'])->name('marketplace-client.licenses.deactivate');
});
Route::middleware(['can:extensions.install', 'password.confirm', 'throttle:10,1'])->group(function () {
    Route::post('extensions/marketplace/licenses/{license}/install', [MarketplaceClientController::class, 'install'])->name('marketplace-client.install');
    Route::post('extensions/marketplace/history/{entry}/rollback', [MarketplaceClientController::class, 'rollback'])->name('marketplace-client.rollback');
});

// Marketplace server administration (platform owner's marketplace instance only).
Route::middleware(['marketplace.server', 'can:marketplace.manage'])->prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('products', [MarketplaceAdminController::class, 'products'])->name('products.index');
    Route::get('products/create', [MarketplaceAdminController::class, 'create'])->name('products.create');
    Route::post('products', [MarketplaceAdminController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [MarketplaceAdminController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [MarketplaceAdminController::class, 'update'])->name('products.update');
    Route::post('products/{product}/releases', [MarketplaceAdminController::class, 'upload'])->middleware('throttle:10,1')->name('releases.store');
    Route::post('releases/{release}/review', [MarketplaceAdminController::class, 'review'])->middleware('password.confirm')->name('releases.review');
    Route::post('products/{product}/advisories', [MarketplaceAdminController::class, 'advisory'])->name('advisories.store');
    Route::post('releases/{release}/withdraw', [MarketplaceAdminController::class, 'withdraw'])->name('releases.withdraw');
    Route::get('reviews', [MarketplaceReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}', [MarketplaceReviewController::class, 'moderate'])->name('reviews.moderate');
    Route::get('sellers', [MarketplaceSellerController::class, 'index'])->name('sellers.index');
    Route::post('sellers/applications/{application}/approve', [MarketplaceSellerController::class, 'approve'])->name('sellers.approve');
    Route::post('sellers/applications/{application}/reject', [MarketplaceSellerController::class, 'reject'])->name('sellers.reject');
    Route::put('sellers/{publisher}', [MarketplaceSellerController::class, 'update'])->name('sellers.update');
    Route::post('sellers/{publisher}/payouts', [MarketplaceSellerController::class, 'payout'])->middleware('password.confirm')->name('sellers.payouts.store');
    Route::get('orders', [MarketplaceAdminController::class, 'orders'])->name('orders.index');
    Route::post('orders/{order}/refund', [MarketplaceAdminController::class, 'refund'])->middleware('password.confirm')->name('orders.refund');
});
Route::middleware(['marketplace.server', 'can:licenses.manage'])->prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('licenses', [MarketplaceAdminController::class, 'licenses'])->name('licenses.index');
    Route::post('licenses/{license}/status', [MarketplaceAdminController::class, 'licenseStatus'])->name('licenses.status');
});

// Extensions. Actions that run, change or remove code and data need a recent password confirmation.
Route::middleware('can:extensions.view')->group(function () {
    Route::get('extensions', [ExtensionController::class, 'index'])->name('extensions.index');
    Route::get('extensions/{extension}', [ExtensionController::class, 'show'])->name('extensions.show');
});
Route::middleware('throttle:30,1')->group(function () {
    Route::post('extensions/{extension}/install', [ExtensionController::class, 'install'])->middleware(['can:extensions.install', 'password.confirm'])->name('extensions.install');
    Route::post('extensions/{extension}/enable', [ExtensionController::class, 'enable'])->middleware(['can:extensions.enable', 'password.confirm'])->name('extensions.enable');
    Route::post('extensions/{extension}/disable', [ExtensionController::class, 'disable'])->middleware('can:extensions.disable')->name('extensions.disable');
    Route::post('extensions/{extension}/update', [ExtensionController::class, 'update'])->middleware(['can:extensions.update', 'password.confirm'])->name('extensions.update');
    Route::put('extensions/{extension}/settings', [ExtensionController::class, 'configure'])->middleware('can:extensions.configure')->name('extensions.settings');
    Route::delete('extensions/{extension}', [ExtensionController::class, 'uninstall'])->middleware(['can:extensions.install', 'password.confirm'])->name('extensions.uninstall');
});

Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
Route::get('system', SystemStatusController::class)->middleware('can:system.view')->name('system.status');
