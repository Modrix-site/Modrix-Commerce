<?php

namespace App\Http\Controllers\Admin;

use App\Core\Admin\AdminNavigation;
use App\Core\Audit\AuditLogger;
use App\Core\Settings\SettingsRepository;
use App\Domains\Payments\Gateways\Stripe\StripeSettings;
use App\Domains\Payments\PaymentMethods;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configures the built-in manual payment methods and guest checkout.
 * Gateway credentials have their own password-confirmed page.
 */
class PaymentSettingsController extends Controller
{
    public function edit(Request $request, AdminNavigation $navigation, PaymentMethods $methods, SettingsRepository $settings, StripeSettings $stripe): Response
    {
        $manual = [];

        foreach (array_keys(PaymentMethods::MANUAL) as $code) {
            $manual[] = ['code' => $code, ...$methods->manualConfig($code)];
        }

        return Inertia::render('admin/payments/settings', [
            'methods' => $manual,
            'gatewayExtensions' => $navigation->paymentGatewaysForUser($request->user()),
            'guestCheckout' => (bool) $settings->get('checkout.guest_enabled', true),
            'stripe' => [
                'enabled' => $stripe->enabled(),
                'configured' => $stripe->enabled() && $stripe->hasCredentials(),
                'mode' => $stripe->mode(),
            ],
        ]);
    }

    public function update(Request $request, SettingsRepository $settings, AuditLogger $audit): RedirectResponse
    {
        $rules = ['guest_checkout' => ['boolean']];
        $attributes = [];

        foreach (array_keys(PaymentMethods::MANUAL) as $code) {
            $rules["methods.{$code}.enabled"] = ['boolean'];
            $rules["methods.{$code}.label"] = ['required', 'string', 'max:120'];
            $rules["methods.{$code}.instructions"] = ['required', 'string', 'max:2000'];
            $attributes["methods.{$code}.label"] = 'name shown to customers';
            $attributes["methods.{$code}.instructions"] = 'instructions';
        }

        $data = $request->validate($rules, [], $attributes);
        $values = ['checkout.guest_enabled' => (bool) ($data['guest_checkout'] ?? false)];

        foreach (array_keys(PaymentMethods::MANUAL) as $code) {
            $values["payments.{$code}.enabled"] = (bool) ($data['methods'][$code]['enabled'] ?? false);
            $values["payments.{$code}.label"] = $data['methods'][$code]['label'];
            $values["payments.{$code}.instructions"] = $data['methods'][$code]['instructions'];
        }

        $settings->setMany($values);
        $audit->record('payments.settings_updated', null, [
            'enabled' => array_keys(array_filter($values, fn ($v, $k) => str_ends_with($k, '.enabled') && $v === true, ARRAY_FILTER_USE_BOTH)),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment settings saved.')]);

        return redirect()->route('admin.payments.settings');
    }
}
