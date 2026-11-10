<?php

namespace EcoModular\PayBy\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\PayBy\PaybyGateway;
use EcoModular\PayBy\PaybySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaybyAdminController extends Controller
{
    public function index(PaybySettings $settings): Response
    {
        $code = PaybyGateway::CODE;

        return Inertia::render('module/modrixcommerce/payby/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.payby.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.payby.update'),
            'testUrl' => route('admin.modrixcommerce.payby.test'),
        ]);
    }

    public function update(
        Request $request,
        PaybySettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'partner_id' => ['required', 'string', 'max:50'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'private_key' => ['nullable', 'string', Rule::requiredIf(! $settings->hasPrivateKey())],
            'payby_public_key' => ['nullable', 'string'],
        ], [
            'partner_id.required' => 'Enter the PayBy Partner ID provided in the merchant portal.',
            'private_key.required' => 'Enter the Merchant RSA Private Key.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('payby.settings_updated', null, [
            'enabled' => $enabled,
            'partner_id' => $settings->partnerId(),
            'test_mode' => $settings->testMode(),
            'private_key_changed' => ! empty($data['private_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('PayBy settings saved.')
                : __('Settings saved. Please provide the Partner ID and Private Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.payby.index');
    }

    public function test(PaybyGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to PayBy API.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.payby.index');
    }
}
