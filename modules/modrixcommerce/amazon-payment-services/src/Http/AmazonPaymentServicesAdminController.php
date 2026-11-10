<?php

namespace EcoModular\AmazonPaymentServices\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesGateway;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AmazonPaymentServicesAdminController extends Controller
{
    public function index(AmazonPaymentServicesSettings $settings): Response
    {
        $code = AmazonPaymentServicesGateway::CODE;

        return Inertia::render('module/modrixcommerce/amazon-payment-services/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', $code),
            'responseUrl' => route('modrixcommerce.amazon-payment-services.response'),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', $code), 'https://'),
            'updateUrl' => route('admin.modrixcommerce.amazon-payment-services.update'),
            'testUrl' => route('admin.modrixcommerce.amazon-payment-services.test'),
        ]);
    }

    public function update(
        Request $request,
        AmazonPaymentServicesSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'merchant_identifier' => ['required', 'string', 'max:20', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'test_mode' => ['required', 'boolean'],
            'sha_type' => ['required', 'string', Rule::in(AmazonPaymentServicesSettings::SHA_TYPES)],
            'command' => ['required', 'string', Rule::in(AmazonPaymentServicesSettings::COMMANDS)],
            'language' => ['required', 'string', Rule::in(AmazonPaymentServicesSettings::LANGUAGES)],
            'label' => ['required', 'string', 'max:100'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'access_code' => ['nullable', 'string', 'max:50', Rule::requiredIf($settings->accessCode() === null)],
            'sha_request_phrase' => ['nullable', 'string', 'max:255', Rule::requiredIf($settings->shaRequestPhrase() === null)],
            'sha_response_phrase' => ['nullable', 'string', 'max:255', Rule::requiredIf($settings->shaResponsePhrase() === null)],
        ], [
            'merchant_identifier.required' => 'Enter the Merchant Identifier from your Amazon Payment Services back-office.',
            'merchant_identifier.regex' => 'Merchant Identifier must contain only letters, numbers, underscores or dashes.',
            'access_code.required' => 'Enter the Access Code from your APS account security settings.',
            'sha_request_phrase.required' => 'Enter the SHA Request Phrase configured in your APS account.',
            'sha_response_phrase.required' => 'Enter the SHA Response Phrase configured in your APS account.',
        ]);

        $enabled = $request->boolean('enabled');

        $settings->update(
            $enabled,
            $data['merchant_identifier'],
            $request->boolean('test_mode'),
            $data['sha_type'],
            $data['command'],
            $data['language'],
            $data['label'],
            $data['currencies'],
            $data['access_code'] ?? null,
            $data['sha_request_phrase'] ?? null,
            $data['sha_response_phrase'] ?? null,
        );

        $audit->record('aps.settings_updated', null, [
            'enabled' => $enabled,
            'merchant_identifier' => $settings->merchantIdentifier(),
            'test_mode' => $settings->testMode(),
            'sha_type' => $settings->shaType(),
            'command' => $settings->command(),
            'access_code_changed' => ! empty($data['access_code']),
            'sha_request_phrase_changed' => ! empty($data['sha_request_phrase']),
            'sha_response_phrase_changed' => ! empty($data['sha_response_phrase']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->ready() || ! $enabled
                ? __('Amazon Payment Services settings saved.')
                : __('Settings saved. Please provide the Merchant Identifier, Access Code, and SHA Phrases to start accepting payments.'),
        ]);

        return redirect()->route('admin.modrixcommerce.amazon-payment-services.index');
    }

    public function test(AmazonPaymentServicesGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Successfully connected to Amazon Payment Services.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.amazon-payment-services.index');
    }
}
