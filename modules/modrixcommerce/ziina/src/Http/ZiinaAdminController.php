<?php

namespace EcoModular\Ziina\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Ziina\ZiinaGateway;
use EcoModular\Ziina\ZiinaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ZiinaAdminController extends Controller
{
    public function index(ZiinaSettings $settings): Response
    {
        $code = ZiinaGateway::CODE;

        return Inertia::render('module/modrixcommerce/ziina/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.ziina.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.ziina.update'),
            'testUrl' => route('admin.modrixcommerce.ziina.test'),
        ]);
    }

    public function update(
        Request $request,
        ZiinaSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'api_key' => ['nullable', 'string', 'max:500', Rule::requiredIf(! $settings->hasApiKey())],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'api_key.required' => 'Enter the Ziina API Key / Personal Access Token.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('ziina.settings_updated', null, [
            'enabled' => $enabled,
            'test_mode' => $settings->testMode(),
            'api_key_changed' => ! empty($data['api_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Ziina settings saved.')
                : __('Settings saved. Please provide the Ziina API Token to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.ziina.index');
    }

    public function test(ZiinaGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Ziina API.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.ziina.index');
    }
}
