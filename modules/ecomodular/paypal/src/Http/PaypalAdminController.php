<?php

namespace EcoModular\Paypal\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Paypal\PaypalApi;
use EcoModular\Paypal\PaypalGateway;
use EcoModular\Paypal\PaypalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaypalAdminController extends Controller
{
    public function index(PaypalSettings $settings): Response
    {
        return Inertia::render('module/ecomodular/paypal/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', PaypalGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', PaypalGateway::CODE), 'https://'),
            'updateUrl' => route('admin.ecomodular.paypal.update'),
            'testUrl' => route('admin.ecomodular.paypal.test'),
        ]);
    }

    public function update(Request $request, PaypalSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'mode' => ['required', 'string', Rule::in(PaypalSettings::MODES)],
            'client_id' => ['required', 'string', 'max:200', 'regex:'.PaypalSettings::CLIENT_PATTERN],
            'webhook_id' => ['required', 'string', 'max:50', 'regex:'.PaypalSettings::WEBHOOK_PATTERN],
            'client_secret' => ['nullable', 'string', 'max:200', 'regex:'.PaypalSettings::CLIENT_PATTERN, Rule::requiredIf($settings->clientSecret() === null)],
        ], [
            'client_id.required' => 'Enter the PayPal REST app client ID.',
            'client_id.regex' => 'Enter the client ID from the PayPal developer dashboard.',
            'webhook_id.required' => 'Enter the PayPal webhook ID.',
            'webhook_id.regex' => 'Enter the webhook ID shown in the PayPal app after you add the webhook.',
            'client_secret.required' => 'Enter the PayPal REST app secret.',
            'client_secret.regex' => 'Enter the secret from the PayPal developer dashboard.',
            'mode.in' => 'Choose sandbox or live.',
        ]);

        $enabled = $request->boolean('enabled');
        $settings->update($enabled, $data['mode'], $data['client_id'], $data['webhook_id'], $data['client_secret'] ?? null);

        $audit->record('paypal.settings_updated', null, [
            'enabled' => $enabled,
            'mode' => $settings->sandbox() ? 'sandbox' : 'live',
            'secret_changed' => ($data['client_secret'] ?? null) !== null && $data['client_secret'] !== '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('PayPal settings saved.')
            : __('PayPal settings saved. Add the client ID, secret and webhook ID to accept payments.')]);

        return redirect()->route('admin.ecomodular.paypal.index');
    }

    public function test(PaypalApi $api): RedirectResponse
    {
        try {
            $api->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to PayPal.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.paypal.index');
    }
}
