<?php

namespace EcoModular\PayU\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\PayU\PayuGateway;
use EcoModular\PayU\PayuSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayuAdminController extends Controller
{
    public function index(PayuSettings $settings): Response
    {
        $code = PayuGateway::CODE;

        return Inertia::render('module/modrixcommerce/payu/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.payu.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.payu.update'),
            'testUrl' => route('admin.modrixcommerce.payu.test'),
        ]);
    }

    public function update(
        Request $request,
        PayuSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'merchant_key' => ['required', 'string', 'max:100'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'salt' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $settings->hasSalt())],
        ], [
            'merchant_key.required' => 'Enter the PayU Merchant Key provided in your PayU Dashboard.',
            'salt.required' => 'Enter the PayU Merchant Salt.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('payu.settings_updated', null, [
            'enabled' => $enabled,
            'merchant_key' => $settings->merchantKey(),
            'test_mode' => $settings->testMode(),
            'salt_changed' => ! empty($data['salt']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('PayU settings saved.')
                : __('Settings saved. Please provide the Merchant Key and Salt to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.payu.index');
    }

    public function test(PayuGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to PayU India.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.payu.index');
    }
}
