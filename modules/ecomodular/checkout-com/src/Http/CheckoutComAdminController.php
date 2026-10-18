<?php

namespace EcoModular\CheckoutCom\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\CheckoutCom\CheckoutComEndpoint;
use EcoModular\CheckoutCom\CheckoutComGateway;
use EcoModular\CheckoutCom\CheckoutComSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutComAdminController extends Controller
{
    public function index(CheckoutComSettings $settings): Response
    {
        return Inertia::render('module/ecomodular/checkout-com/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', CheckoutComGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', CheckoutComGateway::CODE), 'https://'),
            'updateUrl' => route('admin.ecomodular.checkout-com.update'),
            'testUrl' => route('admin.ecomodular.checkout-com.test'),
        ]);
    }

    public function update(Request $request, CheckoutComSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'api_base' => ['required', 'string', 'max:200'],
            'display' => ['required', 'string', Rule::in(CheckoutComSettings::DISPLAYS)],
            'public_key' => ['nullable', 'string', 'max:220', 'regex:'.CheckoutComSettings::PUBLIC_PATTERN],
            'processing_channel_id' => ['nullable', 'string', 'max:50', 'regex:'.CheckoutComSettings::CHANNEL_PATTERN],
            'secret_key' => ['nullable', 'string', 'max:220', 'regex:'.CheckoutComSettings::SECRET_PATTERN, Rule::requiredIf($settings->secretKey() === null)],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^[^\s]{8,255}$/', Rule::requiredIf($settings->webhookSecret() === null)],
        ], [
            'api_base.required' => 'Enter the Checkout.com API base URL.',
            'public_key.regex' => 'Enter a Checkout.com public key (pk_ or pk_sbox_).',
            'processing_channel_id.regex' => 'Enter a processing channel id that starts with pc_, or leave it blank.',
            'secret_key.required' => 'Enter the Checkout.com secret key.',
            'secret_key.regex' => 'Enter a Checkout.com secret key (sk_ or sk_sbox_).',
            'webhook_secret.required' => 'Enter the webhook signature key from the Checkout.com dashboard.',
            'webhook_secret.regex' => 'Enter the webhook signature key from the Checkout.com dashboard.',
            'display.in' => 'Choose the hosted payment page or Flow.',
        ]);

        if (! CheckoutComEndpoint::isApiBase($data['api_base'])) {
            throw ValidationException::withMessages([
                'api_base' => 'Enter an https Checkout.com API base URL, such as https://api.sandbox.checkout.com.',
            ]);
        }

        $secret = ($data['secret_key'] ?? null) !== null && $data['secret_key'] !== ''
            ? $data['secret_key']
            : $settings->secretKey();
        $publicKey = ($data['public_key'] ?? null) !== null && $data['public_key'] !== ''
            ? $data['public_key']
            : ($data['display'] === 'flow' ? $settings->publicKey() : '');

        if ($data['display'] === 'flow' && ($publicKey === null || $publicKey === '')) {
            return back()->withErrors(['public_key' => 'Flow needs the Checkout.com public key.'])->withInput();
        }

        if (is_string($secret) && str_starts_with($secret, 'sk_sbox_') !== CheckoutComEndpoint::isSandboxHost($data['api_base'])) {
            return back()->withErrors(['api_base' => 'The secret key and API base URL must both be sandbox, or both be live.'])->withInput();
        }

        if (is_string($publicKey) && $publicKey !== '' && str_starts_with($publicKey, 'pk_sbox_') !== str_starts_with((string) $secret, 'sk_sbox_')) {
            return back()->withErrors(['public_key' => 'The public key must be from the same Checkout.com environment as the secret key.'])->withInput();
        }

        $enabled = $request->boolean('enabled');
        $settings->update(
            $enabled,
            $data['api_base'],
            $data['display'],
            is_string($publicKey) ? $publicKey : '',
            $data['processing_channel_id'] ?? '',
            $data['secret_key'] ?? null,
            $data['webhook_secret'] ?? null,
        );

        $audit->record('checkout_com.settings_updated', null, [
            'enabled' => $enabled,
            'display' => $settings->display(),
            'sandbox' => $settings->sandbox(),
            'secret_key_changed' => ($data['secret_key'] ?? null) !== null && $data['secret_key'] !== '',
            'webhook_secret_changed' => ($data['webhook_secret'] ?? null) !== null && $data['webhook_secret'] !== '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $settings->ready() || ! $enabled
            ? __('Checkout.com settings saved.')
            : __('Checkout.com settings saved. Add the API URL, secret key and webhook signature key to accept payments.')]);

        return redirect()->route('admin.ecomodular.checkout-com.index');
    }

    public function test(CheckoutComGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Checkout.com.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.checkout-com.index');
    }
}
