<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\Countries;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\ThemeResolver;
use App\Domains\Cart\CartManager;
use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\CheckoutFieldRules;
use App\Domains\Checkout\PlaceOrder;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\Steps\ShippingStep;
use App\Domains\Customers\AddressRules;
use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentMethod;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Payments\PaymentReconciler;
use App\Domains\Payments\PaymentStarter;
use App\Domains\Shipping\DeliveryArea;
use App\Domains\Shipping\ShippingCoverage;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public const SESSION_ORDERS = 'checkout.placed_orders';

    public function __construct(
        private readonly CartManager $carts,
        private readonly CartPricer $pricer,
        private readonly PaymentMethods $paymentMethods,
    ) {}

    public function show(Request $request, PlaceOrder $placeOrder, CustomerProfiles $profiles, StorefrontSlots $slots): Response|RedirectResponse
    {
        $user = $this->user($request);
        $cart = $this->carts->current($request->session(), $user);

        if ($cart === null || ! $cart->items()->exists()) {
            return redirect()->route('cart.show');
        }

        if ($user === null && ! $placeOrder->guestCheckoutEnabled()) {
            return redirect()->guest(route('login'))->with('login_reason', 'checkout');
        }

        // Destination and method are only used to quote; amounts always come from the server.
        $destination = $request->validate([
            'country' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_method' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'string', 'max:64'],
        ]);

        $customer = $user !== null ? $profiles->forUser($user) : null;

        // Priced once per request, shared by the summary and the payment method slots.
        $priced = null;
        $price = function () use (&$priced, $cart, $destination, $customer, $request) {
            return $priced ??= $this->pricer->price($cart, new PricingContext(
                customerId: $customer?->id,
                email: $customer->email ?? $request->string('email')->toString() ?: null,
                countryCode: $destination['country'] ?? null,
                region: $destination['region'] ?? null,
                postalCode: $destination['postal_code'] ?? null,
                shippingMethod: $destination['shipping_method'] ?? null,
                paymentMethod: $destination['payment_method'] ?? array_key_first($this->paymentMethods->enabled()),
                city: $destination['city'] ?? null,
            ));
        };

        return Inertia::render('storefront/checkout', [
            'summary' => fn () => $price()->toArray(),
            'slots' => fn () => $slots->render('checkout.after_summary', ['cart' => $price()]),
            'paymentSlots' => fn () => array_values(array_filter(
                $slots->render('checkout.payment_method', ['cart' => $price()]),
                fn (array $item) => is_string($item['props']['method'] ?? null),
            )),
            'paymentMethods' => fn () => array_values(array_map(fn (PaymentMethod $m) => [
                'code' => $m->code(),
                'label' => $m->label(),
                'instructions' => $m->instructions(),
                'fee' => $m->code() === 'cash_on_delivery' && $price()->requiresShipping()
                    ? $this->paymentMethods->cashOnDeliveryFee($price()->currency)
                    : null,
            ], $this->paymentMethods->enabled())),
            'addresses' => fn () => $customer?->addresses()->get()->map(fn (CustomerAddress $a) => $a->toPresentable())->all() ?? [],
            'email' => $user?->email,
            'countries' => fn () => $price()->requiresShipping() ? app(ShippingCoverage::class)->options($price()->currency) : Countries::options(),
            'soleCountry' => fn () => $price()->requiresShipping() ? app(ShippingCoverage::class)->sole($price()->currency) : null,
            'deliveryRestriction' => fn () => $price()->requiresShipping() && str_starts_with(app(DeliveryArea::class)->toArray()['mode'], 'store_') ? app(DeliveryArea::class)->label() : null,
            'checkoutToken' => fn () => (string) Str::uuid(),
            'placeSlots' => fn () => app(StorefrontSlots::class)->renderMany(['checkout.after_shipping', 'checkout.before_place_order'], ['cart' => $price()]),
            'pageCheckout' => fn () => $this->checkoutSettings($request),
            'termsUrl' => fn () => CheckoutFieldRules::termsUrl(),
        ]);
    }

    /**
     * The merchant's Checkout page settings (customizer).
     *
     * @return array<string, mixed>
     */
    private function checkoutSettings(Request $request): array
    {
        return PageSettingFields::resolve(app(ThemeResolver::class)->forRequest($request)->design)['checkout'];
    }

    public function store(Request $request, PlaceOrder $placeOrder, PaymentStarter $starter): SymfonyResponse
    {
        $user = $this->user($request);
        $cart = $this->carts->current($request->session(), $user);
        abort_if($cart === null, 404);

        $request->merge(['billing_same' => $request->boolean('billing_same', true), 'save_address' => $request->boolean('save_address')]);
        $billingSame = $request->boolean('billing_same');

        // A cart of digital products only has nothing to ship.
        if (! $request->filled('shipping_method') && ! $this->pricer->price($cart)->requiresShipping()) {
            $request->merge(['shipping_method' => ShippingStep::DIGITAL]);
        }

        $checkout = $this->checkoutSettings($request);
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            ...AddressRules::rules('shipping.'),
            'billing_same' => ['boolean'],
            ...($billingSame ? [] : AddressRules::rules('billing.')),
            'shipping_method' => ['required', 'string', 'max:64'],
            'payment_method' => ['required', 'string', 'max:64'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'checkout_token' => ['required', 'uuid'],
            'expected_total' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'save_address' => ['boolean'],
            ...CheckoutFieldRules::rules($checkout),
        ], CheckoutFieldRules::messages());
        // Fields the merchant hides are never stored.
        $validated = CheckoutFieldRules::clean($validated, $checkout);

        $order = $placeOrder->handle($cart, new CheckoutData(
            email: $user->email ?? $validated['email'],
            phone: $validated['phone'] ?? null,
            shippingAddress: $validated['shipping'],
            billingAddress: $billingSame ? null : $validated['billing'],
            shippingMethod: $validated['shipping_method'],
            paymentMethod: $validated['payment_method'],
            customerNote: $validated['customer_note'] ?? null,
            idempotencyKey: $validated['checkout_token'],
            expectedTotal: $validated['expected_total'] ?? null,
            saveAddress: (bool) $validated['save_address'],
        ), $user, $request->ip());

        // Lets a guest view their own confirmation page in this session only.
        $placed = (array) $request->session()->get(self::SESSION_ORDERS, []);
        $request->session()->put(self::SESSION_ORDERS, array_values(array_unique([...$placed, $order->id])));

        if ($this->paymentMethods->isGateway($order->payment_method_code) && $order->payment_status->isOutstanding()) {
            return $this->redirectToPayment($order, $starter);
        }

        return redirect()->route('checkout.complete', $order);
    }

    /**
     * Sends the customer (back) to the hosted payment page of an unpaid online order.
     */
    public function pay(Request $request, Order $order, PaymentStarter $starter): SymfonyResponse
    {
        $this->ensureOwner($request, $order);

        return $this->redirectToPayment($order, $starter);
    }

    /**
     * Where the provider sends the customer after paying. The redirect itself
     * proves nothing: the order is only marked paid if the server, using its
     * secret key, confirms the payment with the provider (ADR-027).
     */
    public function paymentReturn(Request $request, Order $order, PaymentReconciler $reconciler): RedirectResponse
    {
        $this->ensureOwner($request, $order);

        $payment = $order->payments()->whereIn('status', Payment::OPEN)->latest('id')->first();
        $lock = $payment === null ? null : Cache::lock("payment-return:{$payment->id}", 5);

        if ($payment !== null && $lock !== null && $lock->get()) {
            try {
                $reconciler->reconcile($payment);
            } catch (GatewayException) {
                // The webhook or the scheduled reconciliation will catch up.
            } finally {
                $lock->release();
            }
        }

        return redirect()->route('checkout.complete', $order);
    }

    public function complete(Request $request, Order $order, OrderPresenter $presenter, StorefrontSlots $slots): Response
    {
        $this->ensureOwner($request, $order);

        $method = $this->paymentMethods->definition($order->payment_method_code);
        $online = $this->paymentMethods->isGateway($order->payment_method_code);
        $latest = $online ? $order->payments()->latest('id')->first() : null;

        return Inertia::render('storefront/order-complete', [
            'order' => $presenter->forCustomer($order),
            'paymentInstructions' => $online ? null : $method?->instructions(),
            'onlinePayment' => $online ? [
                'state' => match (true) {
                    ! $order->payment_status->isOutstanding() => 'paid',
                    $order->status === OrderStatus::Cancelled => 'cancelled',
                    $latest?->status === Payment::STATUS_PROCESSING => 'processing',
                    $latest?->status === Payment::STATUS_PENDING && $request->query('payment') !== 'cancelled' => 'confirming',
                    default => 'awaiting',
                },
                'method' => $order->payment_method_name,
            ] : null,
            'slots' => $slots->render('order.confirmation', ['order' => $order]),
        ]);
    }

    private function redirectToPayment(Order $order, PaymentStarter $starter): SymfonyResponse
    {
        try {
            $payment = $starter->start(
                $order,
                route('checkout.payment.return', $order),
                route('checkout.complete', [$order, 'payment' => 'cancelled']),
            );
        } catch (GatewayException|ValidationException $e) {
            $message = match (true) {
                $e instanceof ValidationException => collect($e->errors())->flatten()->first(),
                $e->customerMessage !== null => $e->customerMessage,
                default => __('We could not open the payment page. Please try again in a moment.'),
            };
            Inertia::flash('toast', ['type' => 'error', 'message' => (string) $message]);

            return redirect()->route('checkout.complete', $order);
        }

        // An external redirect: Inertia turns this into a full page visit.
        return Inertia::location((string) $payment->checkout_url);
    }

    /**
     * Guests may see orders placed in this browser session; customers their own orders.
     */
    private function ensureOwner(Request $request, Order $order): void
    {
        $user = $this->user($request);
        $ownsBySession = in_array($order->id, (array) $request->session()->get(self::SESSION_ORDERS, []), true);
        $ownsByAccount = $user !== null && $order->user_id === $user->id;
        abort_unless($ownsBySession || $ownsByAccount, 404);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
