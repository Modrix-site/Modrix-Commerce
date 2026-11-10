<?php

namespace EcoModular\CartAbandonmentRecovery\Http\Controllers;

use App\Domains\Cart\CartManager;
use App\Http\Controllers\Controller;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartEmail;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartUnsubscribe;
use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;

class CartRecoveryStorefrontController extends Controller
{
    public function __construct(
        private readonly CartRecoveryService $service,
        private readonly CartManager $cartManager,
    ) {}

    public function recover(Request $request, string $token): RedirectResponse
    {
        $cart = $this->service->restoreCartFromToken($token, $request->session(), $request->user());

        if ($cart !== null) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Welcome back! We restored your items to your cart.',
            ]);

            return redirect()->route('checkout.show');
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'This recovery link is no longer valid or has expired.',
        ]);

        return redirect()->route('cart.show');
    }

    public function captureEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $cart = $this->cartManager->current($request->session(), $request->user(), create: false);

        if ($cart === null || ! $cart->items()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cart is empty.'], 422);
        }

        $record = $this->service->captureEmail($cart, $validated['email'], $validated['name'] ?? null, $validated['phone'] ?? null);

        return response()->json([
            'success' => true,
            'recovery_token' => $record->recovery_token,
        ]);
    }

    public function emailCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $cart = $this->cartManager->current($request->session(), $request->user(), create: false);

        if ($cart === null || ! $cart->items()->exists()) {
            return response()->json(['success' => false, 'message' => 'Your cart is currently empty.'], 422);
        }

        $record = $this->service->captureEmail($cart, $validated['email'], $validated['name'] ?? null);

        $sent = $this->service->sendSequenceEmail(
            $record,
            1,
            'Your saved shopping cart',
            $record->applied_coupon_code
        );

        if ($sent) {
            $record->update([
                'emails_sent_count' => 1,
                'last_email_sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'We have emailed a direct link to your cart!',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Could not send email at this moment. Please try again.',
        ], 500);
    }

    public function unsubscribe(Request $request, string $token): RedirectResponse
    {
        $record = AbandonedCart::query()->where('recovery_token', $token)->first();

        if ($record !== null) {
            AbandonedCartUnsubscribe::query()->firstOrCreate(
                ['email' => $record->email],
                ['reason' => 'Unsubscribed via email link']
            );

            $record->update([
                'status' => AbandonedCart::STATUS_UNSUBSCRIBED,
                'next_email_scheduled_at' => null,
            ]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'You have been unsubscribed from cart reminders.',
            ]);
        }

        return redirect()->route('home');
    }

    public function trackOpen(AbandonedCartEmail $email): HttpResponse
    {
        $email->recordOpen();

        // 1x1 transparent GIF binary
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function trackClick(AbandonedCartEmail $email): RedirectResponse
    {
        $email->recordClick();

        $cart = $email->abandonedCart;

        return redirect()->to($cart !== null ? $cart->recoveryUrl() : route('home'));
    }
}
