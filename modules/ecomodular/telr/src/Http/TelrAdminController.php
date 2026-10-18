<?php

namespace EcoModular\Telr\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Telr\TelrGateway;
use EcoModular\Telr\TelrSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TelrAdminController extends Controller
{
    public function index(TelrSettings $settings): Response
    {
        return Inertia::render('module/ecomodular/telr/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', TelrGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', TelrGateway::CODE), 'https://'),
            'updateUrl' => route('admin.ecomodular.telr.update'),
            'testUrl' => route('admin.ecomodular.telr.test'),
        ]);
    }

    public function update(Request $request, TelrSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'store_id' => ['required', 'string', 'regex:'.TelrSettings::STORE_ID_PATTERN],
            'test_mode' => ['required', 'boolean'],
            'display' => ['required', 'string', Rule::in(TelrSettings::DISPLAYS)],
            'auth_key' => ['nullable', 'string', 'max:255', 'regex:'.TelrSettings::SECRET_PATTERN, Rule::requiredIf($settings->authKey() === null)],
            'remote_auth_key' => ['nullable', 'string', 'max:255', 'regex:'.TelrSettings::SECRET_PATTERN],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'regex:'.TelrSettings::SECRET_PATTERN, Rule::requiredIf($settings->webhookSecret() === null)],
        ], [
            'store_id.regex' => 'Enter the numeric Telr store ID.',
            'auth_key.required' => 'Enter the Telr authentication key.',
            'auth_key.regex' => 'Enter the authentication key from the Telr merchant admin.',
            'remote_auth_key.regex' => 'Enter the remote authentication key, or leave it blank to use the hosted payment page key.',
            'webhook_secret.required' => 'Enter the Telr secret key used to sign transaction advice.',
            'webhook_secret.regex' => 'Enter the Telr secret key used to sign transaction advice.',
            'display.in' => 'Choose the hosted payment page or the iframe checkout.',
        ]);

        $enabled = $request->boolean('enabled');
        $settings->update(
            $enabled,
            $data['store_id'],
            $request->boolean('test_mode'),
            $data['display'],
            $data['auth_key'] ?? null,
            $data['remote_auth_key'] ?? null,
            $data['webhook_secret'] ?? null,
        );

        $audit->record('telr.settings_updated', null, [
            'enabled' => $enabled,
            'test_mode' => $settings->testMode(),
            'display' => $settings->display(),
            'auth_key_changed' => ($data['auth_key'] ?? null) !== null && $data['auth_key'] !== '',
            'remote_auth_key_changed' => ($data['remote_auth_key'] ?? null) !== null && $data['remote_auth_key'] !== '',
            'webhook_secret_changed' => ($data['webhook_secret'] ?? null) !== null && $data['webhook_secret'] !== '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('Telr settings saved.')
            : __('Telr settings saved. Add the store ID, authentication key and webhook secret to accept payments.')]);

        return redirect()->route('admin.ecomodular.telr.index');
    }

    public function test(TelrGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Telr.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.telr.index');
    }
}
