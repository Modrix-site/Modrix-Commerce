<?php

namespace EcoModular\PhonePe\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\PhonePe\PhonePeGateway;
use EcoModular\PhonePe\PhonePeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PhonePeAdminController extends Controller
{
    public function index(PhonePeSettings $settings): Response
    {
        $code = PhonePeGateway::CODE;

        return Inertia::render('module/modrixcommerce/phonepe/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.phonepe.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.phonepe.update'),
            'testUrl' => route('admin.modrixcommerce.phonepe.test'),
        ]);
    }

    public function update(
        Request $request,
        PhonePeSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'merchant_id' => ['required', 'string', 'max:50'],
            'salt_index' => ['required', 'string', 'max:10'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'salt_key' => ['nullable', 'string', 'max:255', Rule::requiredIf($settings->saltKey() === null)],
        ], [
            'merchant_id.required' => 'Enter the PhonePe Merchant ID provided by PhonePe onboarding.',
            'salt_key.required' => 'Enter the PhonePe Salt Key provided by PhonePe onboarding.',
            'salt_index.required' => 'Enter the Salt Index (typically 1).',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->update(
            $enabled,
            $data['merchant_id'],
            $request->boolean('test_mode'),
            $data['salt_index'],
            $data['label'],
            $data['currencies'],
            $data['salt_key'] ?? null,
        );

        $audit->record('phonepe.settings_updated', null, [
            'enabled' => $enabled,
            'merchant_id' => $settings->merchantId(),
            'test_mode' => $settings->testMode(),
            'salt_key_changed' => ! empty($data['salt_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('PhonePe settings saved.')
                : __('Settings saved. Please provide the Merchant ID and Salt Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.phonepe.index');
    }

    public function test(PhonePeGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to PhonePe.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.phonepe.index');
    }
}
