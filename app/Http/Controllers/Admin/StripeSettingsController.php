<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateways\Stripe\StripeGateway;
use App\Domains\Payments\Gateways\Stripe\StripeSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stripe credentials. Changing them redirects where customers' money goes,
 * so the page requires a recent password confirmation and every change is
 * audited. Secrets are write-only: the page only ever receives a hint.
 */
class StripeSettingsController extends Controller
{
    public function edit(StripeSettings $settings): Response
    {
        return Inertia::render('admin/payments/stripe', [
            'stripe' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', 'stripe'),
            'events' => [
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded',
                'checkout.session.async_payment_failed',
                'checkout.session.expired',
                'refund.created',
                'refund.updated',
                'refund.failed',
            ],
        ]);
    }

    public function update(Request $request, StripeSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'label' => ['required', 'string', 'max:120'],
            'apple_pay' => ['boolean'],
            'google_pay' => ['boolean'],
            'secret_key' => ['nullable', 'string', 'max:255', 'regex:'.StripeSettings::KEY_PATTERN],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'regex:'.StripeSettings::WEBHOOK_SECRET_PATTERN],
        ], [
            'secret_key.regex' => 'Enter a Stripe secret or restricted key (sk_test_…, sk_live_…, rk_test_… or rk_live_…).',
            'webhook_secret.regex' => 'Enter the webhook signing secret (whsec_…).',
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $newKey = $data['secret_key'] ?? null;
        $newWebhookSecret = $data['webhook_secret'] ?? null;

        if ($enabled) {
            $request->validate([
                'secret_key' => [Rule::requiredIf($settings->secretKey() === null)],
                'webhook_secret' => [Rule::requiredIf($settings->webhookSecret() === null)],
            ], [
                'secret_key.required' => 'A secret key is required to enable Stripe.',
                'webhook_secret.required' => 'A webhook signing secret is required to enable Stripe. Without it payments cannot be confirmed.',
            ]);
        }

        $settings->update($enabled, $data['label'], $newKey, $newWebhookSecret, $request->boolean('apple_pay'), $request->boolean('google_pay'));

        $audit->record('payments.stripe_updated', null, [
            'enabled' => $enabled,
            'apple_pay' => $settings->applePay(),
            'google_pay' => $settings->googlePay(),
            'mode' => $settings->mode(),
            'changed' => array_values(array_filter([
                $newKey !== null ? 'api key' : null,
                $newWebhookSecret !== null ? 'webhook signing key' : null,
            ])),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stripe settings saved.')]);

        return redirect()->route('admin.payments.stripe.edit');
    }

    public function test(StripeGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Stripe.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.payments.stripe.edit');
    }
}
