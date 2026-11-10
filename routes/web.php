<?php

use App\Domains\Content\Models\Page;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AccountNotificationController;
use App\Http\Controllers\Storefront\AccountOrderController;
use App\Http\Controllers\Storefront\AccountOrderDocumentController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\DownloadController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Middleware\ShareStorefrontData;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::middleware(['installed', ShareStorefrontData::class])->group(function () {
    Route::get('pages/{slug}', function (string $slug) {
        return Inertia::render('storefront/page', ['page' => Page::query()->where('slug', $slug)->where('status', 'published')->firstOrFail()->forStorefront()]);
    })->name('storefront.page');
    Route::get('/', [CatalogController::class, 'home'])->name('home');
    Route::get('search', [CatalogController::class, 'search'])->name('storefront.search');
    Route::get('c/{slug}', [CatalogController::class, 'category'])->name('storefront.category');
    Route::get('b/{slug}', [CatalogController::class, 'brand'])->name('storefront.brand');
    Route::get('p/{slug}', [ProductController::class, 'show'])->name('storefront.product');

    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
        Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
        Route::delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
    });

    // Shopper sign-in and sign-up, inside the theme's header and footer (same names as Fortify's).
    Route::middleware('guest:'.config('fortify.guard'))->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        if (Features::enabled(Features::resetPasswords())) {
            Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
            Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        }
    });

    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('checkout/complete/{order}', [CheckoutController::class, 'complete'])->name('checkout.complete');
    // Purchased files; the 40-character token is the secret.
    Route::get('downloads/{token}', [DownloadController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware('throttle:30,1')
        ->name('storefront.downloads.show');
    Route::post('checkout/orders/{order}/pay', [CheckoutController::class, 'pay'])->middleware('throttle:checkout')->name('checkout.pay');
    Route::get('checkout/orders/{order}/payment/return', [CheckoutController::class, 'paymentReturn'])->middleware('throttle:30,1')->name('checkout.payment.return');

    Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
        Route::get('pages/{slug}', function (string $slug) {
            return Inertia::render('storefront/page', ['page' => Page::query()->where('slug', $slug)->where('status', 'published')->firstOrFail()->forStorefront()]);
        })->name('storefront.page');
        Route::get('/', [AccountController::class, 'show'])->name('show');
        Route::get('addresses', [AccountController::class, 'addresses'])->name('addresses');
        Route::get('data-export', [AccountController::class, 'exportData'])->middleware('throttle:5,1')->name('data-export');
        Route::post('addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::put('addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('security', [SecurityController::class, 'edit'])->middleware(['verified', RequirePassword::using('account.password.confirm')])->name('security');
        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
        Route::get('password', [SecurityController::class, 'password'])->middleware('verified')->name('password');
        Route::put('password', [SecurityController::class, 'update'])->middleware(['verified', 'throttle:6,1'])->name('password.update');
        Route::get('orders', [AccountOrderController::class, 'index'])->name('orders');
        Route::get('orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
        Route::get('notifications', [AccountNotificationController::class, 'index'])->name('notifications');
        Route::get('notifications/feed', [AccountNotificationController::class, 'feed'])->middleware('throttle:20,1')->name('notifications.feed');
        Route::post('notifications/read-all', [AccountNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/open', [AccountNotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('orders/{order}/invoice.pdf', [AccountOrderDocumentController::class, 'invoice'])->name('orders.invoice');
            Route::get('orders/{order}/payments/{payment}/receipt.pdf', [AccountOrderDocumentController::class, 'receipt'])->scopeBindings()->name('orders.receipt');
        });
    });
});

Route::middleware(['installed', 'auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
});

// Staff sign-in; posts to the shared Fortify login.
Route::middleware(['installed', 'guest:'.config('fortify.guard')])
    ->get('admin/login', AdminLoginController::class)
    ->name('admin.login');

if (Features::enabled(Features::resetPasswords())) {
    Route::middleware(['installed', 'guest:'.config('fortify.guard')])->prefix('admin')->name('admin.')->group(function () {
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    });
}

// XML sitemap, contributed by modules with the "sitemap.provider" capability.
Route::middleware('installed')->get('sitemap.xml', SitemapController::class)->name('sitemap');

// Fortify registers these without an IP limit; redeclared here (same names) with one.
Route::middleware(['web', 'guest:'.config('fortify.guard'), 'throttle:auth-sensitive'])->group(function () {
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
});

require __DIR__.'/settings.php';
