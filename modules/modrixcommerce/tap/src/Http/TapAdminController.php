<?php

namespace EcoModular\Tap\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Tap\TapApi;
use EcoModular\Tap\TapGateway;
use EcoModular\Tap\TapSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TapAdminController extends Controller
{
    public function index(TapSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/tap/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', TapGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', TapGateway::CODE), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.tap.update'),
            'testUrl' => route('admin.modrixcommerce.tap.test'),
        ]);
    }

    public function update(Request $request, TapSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'source' => ['required', 'string', Rule::in(TapSettings::SOURCES)],
            'merchant_id' => ['nullable', 'string', 'max:64', 'regex:'.TapSettings::MERCHANT_PATTERN],
            'secret_key' => ['nullable', 'string', 'max:140', 'regex:'.TapSettings::SECRET_PATTERN, Rule::requiredIf($settings->secret() === null)],
        ], [
            'source.in' => 'Choose cards only, or every method enabled on the Tap account.',
            'merchant_id.regex' => 'Enter the merchant ID from the Tap dashboard, or leave it blank.',
            'secret_key.required' => 'Enter the Tap secret key.',
            'secret_key.regex' => 'Enter a Tap secret key starting with sk_test_ or sk_live_.',
        ]);

        $enabled = $request->boolean('enabled');
        $settings->update($enabled, $data['source'], (string) ($data['merchant_id'] ?? ''), $data['secret_key'] ?? null);

        $audit->record('tap.settings_updated', null, [
            'enabled' => $enabled,
            'mode' => $settings->live() ? 'live' : 'sandbox',
            'source' => $settings->sourceId(),
            'secret_changed' => ($data['secret_key'] ?? null) !== null && $data['secret_key'] !== '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('Tap settings saved.')
            : __('Tap settings saved. Add the secret key to accept payments.')]);

        return redirect()->route('admin.modrixcommerce.tap.index');
    }

    public function test(TapApi $api): RedirectResponse
    {
        try {
            $api->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Tap.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.tap.index');
    }
}
