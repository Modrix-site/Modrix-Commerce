<?php

namespace EcoModular\Tamara\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use EcoModular\Tamara\TamaraGateway;
use EcoModular\Tamara\TamaraSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TamaraAdminController extends Controller
{
    public function index(TamaraSettings $settings): Response
    {
        return Inertia::render('module/ecomodular/tamara/settings', [
            'settings' => $settings->toArray(),
            'webhookUrl' => route('webhooks.payments', TamaraGateway::CODE),
            'publicWebhookUrl' => str_starts_with(route('webhooks.payments', TamaraGateway::CODE), 'https://'),
            'updateUrl' => route('admin.ecomodular.tamara.update'),
            'testUrl' => route('admin.ecomodular.tamara.test'),
        ]);
    }

    public function update(Request $request, TamaraSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'environment' => ['required', 'string', Rule::in(TamaraSettings::ENVIRONMENTS)],
            'api_token' => ['nullable', 'string', 'max:500', Rule::requiredIf($settings->apiToken() === null)],
            'notification_token' => ['nullable', 'string', 'max:500', Rule::requiredIf($settings->notificationToken() === null)],
        ], [
            'api_token.required' => 'Enter the Tamara API token.',
            'notification_token.required' => 'Enter the Tamara notification token used to sign webhooks.',
            'environment.in' => 'Choose sandbox or production.',
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $apiToken = $data['api_token'] ?? null;
        $notificationToken = $data['notification_token'] ?? null;

        $settings->update($enabled, $data['environment'], $apiToken, $notificationToken);
        $audit->record('tamara.settings_updated', null, [
            'enabled' => $enabled,
            'environment' => $settings->environment(),
            'api_token_changed' => $apiToken !== null,
            'notification_token_changed' => $notificationToken !== null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tamara settings saved.')]);

        return redirect()->route('admin.ecomodular.tamara.index');
    }

    public function test(TamaraGateway $gateway): RedirectResponse
    {
        try {
            $gateway->testConnection();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Tamara.')]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.ecomodular.tamara.index');
    }
}
