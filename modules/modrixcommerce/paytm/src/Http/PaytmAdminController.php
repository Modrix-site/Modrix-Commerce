<?php

namespace EcoModular\Paytm\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Paytm\PaytmGateway;
use EcoModular\Paytm\PaytmSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaytmAdminController extends Controller
{
    public function index(PaytmSettings $settings): Response
    {
        $code = PaytmGateway::CODE;

        return Inertia::render('module/modrixcommerce/paytm/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.paytm.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.paytm.update'),
            'testUrl' => route('admin.modrixcommerce.paytm.test'),
        ]);
    }

    public function update(
        Request $request,
        PaytmSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'mid' => ['required', 'string', 'max:50'],
            'test_mode' => ['required', 'boolean'],
            'website' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'merchant_key' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $settings->hasMerchantKey())],
        ], [
            'mid.required' => 'Enter the Paytm Merchant ID (MID) from your Paytm Dashboard.',
            'merchant_key.required' => 'Enter the Paytm Merchant Key.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('paytm.settings_updated', null, [
            'enabled' => $enabled,
            'mid' => $settings->mid(),
            'test_mode' => $settings->testMode(),
            'merchant_key_changed' => ! empty($data['merchant_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Paytm settings saved.')
                : __('Settings saved. Please provide the Merchant ID (MID) and Merchant Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.paytm.index');
    }

    public function test(PaytmGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Paytm Payment Gateway.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.paytm.index');
    }
}
