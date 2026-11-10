<?php

namespace EcoModular\NGenius\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\NGenius\NGeniusGateway;
use EcoModular\NGenius\NGeniusSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NGeniusAdminController extends Controller
{
    public function index(NGeniusSettings $settings): Response
    {
        $code = NGeniusGateway::CODE;

        return Inertia::render('module/modrixcommerce/ngenius/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.ngenius.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.ngenius.update'),
            'testUrl' => route('admin.modrixcommerce.ngenius.test'),
        ]);
    }

    public function update(
        Request $request,
        NGeniusSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'outlet_id' => ['required', 'string', 'max:100'],
            'test_mode' => ['required', 'boolean'],
            'action' => ['required', 'string', Rule::in(NGeniusSettings::ACTIONS)],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'api_key' => ['nullable', 'string', 'max:500', Rule::requiredIf(! $settings->hasApiKey())],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'outlet_id.required' => 'Enter the N-Genius Outlet Reference ID provided in the Network portal.',
            'api_key.required' => 'Enter the N-Genius Service Account API Key.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('ngenius.settings_updated', null, [
            'enabled' => $enabled,
            'outlet_id' => $settings->outletId(),
            'test_mode' => $settings->testMode(),
            'api_key_changed' => ! empty($data['api_key']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Network International (N-Genius) settings saved.')
                : __('Settings saved. Please provide the Outlet Reference ID and API Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.ngenius.index');
    }

    public function test(NGeniusGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Network International (N-Genius).')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.ngenius.index');
    }
}
