<?php

namespace EcoModular\Cashfree\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Cashfree\CashfreeGateway;
use EcoModular\Cashfree\CashfreeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashfreeAdminController extends Controller
{
    public function index(CashfreeSettings $settings): Response
    {
        $code = CashfreeGateway::CODE;

        return Inertia::render('module/modrixcommerce/cashfree/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.cashfree.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.cashfree.update'),
            'testUrl' => route('admin.modrixcommerce.cashfree.test'),
        ]);
    }

    public function update(
        Request $request,
        CashfreeSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'app_id' => ['required', 'string', 'max:50'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'secret_key' => ['nullable', 'string', 'max:255', Rule::requiredIf($settings->secretKey() === null)],
        ], [
            'app_id.required' => 'Enter the Cashfree App ID from your Cashfree merchant dashboard.',
            'secret_key.required' => 'Enter the Cashfree Secret Key from your Cashfree merchant dashboard.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->update(
            $enabled,
            $data['app_id'],
            $request->boolean('test_mode'),
            $data['label'],
            $data['currencies'],
            $data['secret_key'] ?? null,
        );

        $audit->record('cashfree.settings_updated', null, [
            'enabled' => $enabled,
            'app_id' => $settings->appId(),
            'test_mode' => $settings->testMode(),
            'secret_key_changed' => ! empty($data['secret_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Cashfree Payments settings saved.')
                : __('Settings saved. Please provide the App ID and Secret Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.cashfree.index');
    }

    public function test(CashfreeGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Cashfree Payments.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.cashfree.index');
    }
}
