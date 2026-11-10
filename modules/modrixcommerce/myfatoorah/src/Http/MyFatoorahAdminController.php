<?php

namespace EcoModular\MyFatoorah\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\MyFatoorah\MyFatoorahGateway;
use EcoModular\MyFatoorah\MyFatoorahSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MyFatoorahAdminController extends Controller
{
    public function index(MyFatoorahSettings $settings): Response
    {
        $code = MyFatoorahGateway::CODE;

        return Inertia::render('module/modrixcommerce/myfatoorah/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.myfatoorah.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.myfatoorah.update'),
            'testUrl' => route('admin.modrixcommerce.myfatoorah.test'),
        ]);
    }

    public function update(
        Request $request,
        MyFatoorahSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'test_mode' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'api_key' => ['nullable', 'string', Rule::requiredIf(! $settings->hasApiKey())],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'api_key.required' => 'Enter the MyFatoorah API Key (Token) provided in your merchant portal.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->save($data);

        $audit->record('myfatoorah.settings_updated', null, [
            'enabled' => $enabled,
            'test_mode' => $settings->testMode(),
            'api_key_changed' => ! empty($data['api_key']),
            'webhook_secret_changed' => ! empty($data['webhook_secret']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('MyFatoorah settings saved.')
                : __('Settings saved. Please provide the API Key to accept payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.myfatoorah.index');
    }

    public function test(MyFatoorahGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to MyFatoorah.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.myfatoorah.index');
    }
}
