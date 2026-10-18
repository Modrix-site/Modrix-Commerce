<?php

namespace EcoModular\Tabby\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Tabby\TabbyApi;
use EcoModular\Tabby\TabbyGateway;
use EcoModular\Tabby\TabbySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TabbyAdminController extends Controller
{
    public function index(TabbySettings $settings): Response
    {
        return Inertia::render('module/ecomodular/tabby/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', TabbyGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', TabbyGateway::CODE), 'https://'),
            'updateUrl' => route('admin.ecomodular.tabby.update'),
            'testUrl' => route('admin.ecomodular.tabby.test'),
            'webhookRegisterUrl' => route('admin.ecomodular.tabby.webhook'),
        ]);
    }

    public function update(Request $request, TabbySettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'public_key' => ['required', 'string', 'max:255', 'regex:'.TabbySettings::PUBLIC_KEY_PATTERN],
            'merchant_code' => ['required', 'string', 'max:64', 'regex:'.TabbySettings::MERCHANT_CODE_PATTERN],
            'secret_key' => ['nullable', 'string', 'max:255', 'regex:'.TabbySettings::SECRET_KEY_PATTERN, Rule::requiredIf($settings->secretKey() === null)],
        ], [
            'public_key.regex' => 'Enter the Tabby public key (pk_test_… or pk_…).',
            'secret_key.regex' => 'Enter the Tabby secret key (sk_test_… or sk_…).',
            'secret_key.required' => 'Enter the Tabby secret key.',
            'merchant_code.regex' => 'Enter the merchant code exactly as Tabby provided it.',
        ]);

        $secret = $data['secret_key'] ?? null;
        $public = $data['public_key'];

        if (str_starts_with($public, 'pk_test_') !== str_starts_with($secret ?? $settings->secretKey() ?? '', 'sk_test_')) {
            return back()->withErrors(['public_key' => 'Use the public and secret keys of the same environment (both test or both live).']);
        }

        $enabled = (bool) ($data['enabled'] ?? false);
        $settings->update($enabled, $public, $data['merchant_code'], $secret);
        $audit->record('tabby.settings_updated', null, ['enabled' => $enabled, 'secret_key_changed' => $secret !== null, 'mode' => $settings->mode()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('Tabby settings saved.')
            : __('Tabby settings saved. Register the webhook to start accepting Tabby payments.')]);

        return redirect()->route('admin.ecomodular.tabby.index');
    }

    public function test(TabbyGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Tabby.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.tabby.index');
    }

    /**
     * Registers (or re-registers) this store's webhook with Tabby. A fresh
     * random secret is sent as a header Tabby echoes on every delivery.
     */
    public function registerWebhook(TabbySettings $settings, TabbyApi $api, AuditLogger $audit): RedirectResponse
    {
        $url = route('webhooks.payments', TabbyGateway::CODE);

        if (! str_starts_with($url, 'https://')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tabby only delivers webhooks to a public https:// address. Set APP_URL to your store\'s https address first.')]);

            return redirect()->route('admin.ecomodular.tabby.index');
        }

        $secret = Str::random(48);
        $body = ['url' => $url, 'header' => ['title' => TabbyGateway::WEBHOOK_HEADER, 'value' => $secret]];

        try {
            $existing = $settings->webhookId();
            $response = $existing !== null
                ? $api->send('put', '/api/v1/webhooks/'.rawurlencode($existing), $body)
                : null;

            // A webhook removed on Tabby's side (404) is registered again.
            if ($response === null || $response->status() === 404) {
                $response = $api->send('post', '/api/v1/webhooks', $body);
            }

            if ($response->failed()) {
                throw TabbyApi::error($response);
            }

            $id = $response->json('id');

            if (! is_string($id) || $id === '') {
                throw new GatewayException('Tabby did not return a webhook id.');
            }

            $settings->rememberWebhook($id, $secret);
            $audit->record('tabby.webhook_registered', null, ['mode' => $settings->mode()]);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook registered with Tabby.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.tabby.index');
    }
}
