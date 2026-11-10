<?php

namespace EcoModular\AuthorizeNet\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\AuthorizeNet\AuthorizeNetApi;
use EcoModular\AuthorizeNet\AuthorizeNetGateway;
use EcoModular\AuthorizeNet\AuthorizeNetSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizeNetAdminController extends Controller
{
    public function index(AuthorizeNetSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/authorize-net/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', AuthorizeNetGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', AuthorizeNetGateway::CODE), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.authorize-net.update'),
            'testUrl' => route('admin.modrixcommerce.authorize-net.test'),
        ]);
    }

    public function update(Request $request, AuthorizeNetSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'mode' => ['required', 'string', Rule::in(AuthorizeNetSettings::MODES)],
            'api_login_id' => ['required', 'string', 'max:25', 'regex:'.AuthorizeNetSettings::LOGIN_PATTERN],
            'transaction_key' => ['nullable', 'string', 'max:128', 'regex:'.AuthorizeNetSettings::TRANSACTION_PATTERN, Rule::requiredIf($settings->transactionKey() === null)],
            'signature_key' => ['nullable', 'string', 'max:256', 'regex:'.AuthorizeNetSettings::SIGNATURE_PATTERN, Rule::requiredIf($settings->signatureKey() === null)],
        ], [
            'api_login_id.required' => 'Enter the API Login ID.',
            'api_login_id.regex' => 'Enter the API Login ID from the Authorize.Net merchant interface.',
            'transaction_key.required' => 'Enter the transaction key.',
            'transaction_key.regex' => 'Enter the transaction key from the Authorize.Net merchant interface.',
            'signature_key.required' => 'Enter the signature key.',
            'signature_key.regex' => 'Enter the signature key generated under API Credentials and Keys. It is a hexadecimal value.',
            'mode.in' => 'Choose sandbox or live.',
        ]);

        if (($data['signature_key'] ?? '') !== '' && strlen($data['signature_key']) % 2 !== 0) {
            throw ValidationException::withMessages([
                'signature_key' => 'Enter the signature key as an even-length hexadecimal value.',
            ]);
        }

        $enabled = $request->boolean('enabled');
        $settings->update($enabled, $data['mode'], $data['api_login_id'], $data['transaction_key'] ?? null, $data['signature_key'] ?? null);

        $audit->record('authorize_net.settings_updated', null, [
            'enabled' => $enabled,
            'mode' => $settings->sandbox() ? 'sandbox' : 'live',
            'transaction_key_changed' => ($data['transaction_key'] ?? null) !== null && $data['transaction_key'] !== '',
            'signature_key_changed' => ($data['signature_key'] ?? null) !== null && $data['signature_key'] !== '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('Authorize.Net settings saved.')
            : __('Authorize.Net settings saved. Add the API Login ID, transaction key and signature key to accept payments.')]);

        return redirect()->route('admin.modrixcommerce.authorize-net.index');
    }

    public function test(AuthorizeNetApi $api): RedirectResponse
    {
        try {
            $api->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Authorize.Net.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.authorize-net.index');
    }
}
