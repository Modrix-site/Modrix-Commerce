<?php

namespace EcoModular\Easebuzz\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Easebuzz\EasebuzzGateway;
use EcoModular\Easebuzz\EasebuzzSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EasebuzzAdminController extends Controller
{
    public function index(EasebuzzSettings $settings): Response
    {
        $code = EasebuzzGateway::CODE;

        return Inertia::render('module/modrixcommerce/easebuzz/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.easebuzz.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.easebuzz.update'),
            'testUrl' => route('admin.modrixcommerce.easebuzz.test'),
        ]);
    }

    public function update(
        Request $request,
        EasebuzzSettings $settings,
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
            'merchant_key.required' => 'Enter the Easebuzz Merchant Key provided in your Easebuzz Dashboard.',
            'salt.required' => 'Enter the Easebuzz Merchant Salt.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('easebuzz.settings_updated', null, [
            'enabled' => $enabled,
            'merchant_key' => $settings->merchantKey(),
            'test_mode' => $settings->testMode(),
            'salt_changed' => ! empty($data['salt']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Easebuzz settings saved.')
                : __('Settings saved. Please provide the Merchant Key and Salt to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.easebuzz.index');
    }

    public function test(EasebuzzGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Easebuzz India.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.easebuzz.index');
    }
}
