<?php

namespace EcoModular\Instamojo\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Instamojo\InstamojoGateway;
use EcoModular\Instamojo\InstamojoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstamojoAdminController extends Controller
{
    public function index(InstamojoSettings $settings): Response
    {
        $code = InstamojoGateway::CODE;

        return Inertia::render('module/modrixcommerce/instamojo/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.instamojo.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.instamojo.update'),
            'testUrl' => route('admin.modrixcommerce.instamojo.test'),
        ]);
    }

    public function update(
        Request $request,
        InstamojoSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'api_key' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $settings->hasApiKey())],
            'auth_token' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $settings->hasAuthToken())],
            'private_salt' => ['nullable', 'string', 'max:255'],
        ], [
            'api_key.required' => 'Enter the Instamojo API Key from your dashboard.',
            'auth_token.required' => 'Enter the Instamojo Auth Token from your dashboard.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('instamojo.settings_updated', null, [
            'enabled' => $enabled,
            'test_mode' => $settings->testMode(),
            'api_key_changed' => ! empty($data['api_key']),
            'auth_token_changed' => ! empty($data['auth_token']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Instamojo settings saved.')
                : __('Settings saved. Please provide the API Key and Auth Token to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.instamojo.index');
    }

    public function test(InstamojoGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Instamojo API.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.instamojo.index');
    }
}
