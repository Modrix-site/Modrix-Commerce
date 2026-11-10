<?php

namespace ModrixCommerce\CCAvenue\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ModrixCommerce\CCAvenue\CCAvenueSettings;

class CCAvenueAdminController extends Controller
{
    public function index(CCAvenueSettings $settings)
    {
        return Inertia::render('module/modrixcommerce/ccavenue/settings', [
            'settings' => $settings->toArray(),
            'currencies' => CCAvenueSettings::CURRENCIES,
            'updateUrl' => route('admin.modrixcommerce.ccavenue.update'),
            'callbackUrl' => route('modrixcommerce.ccavenue.response'),
            'webhookUrl' => route('webhooks.payments', 'ccavenue'),
        ]);
    }

    public function update(Request $request, CCAvenueSettings $settings, AuditLogger $audit)
    {
        $rules = [];
        foreach (['enabled', 'test_mode', 'callbacks_enabled', 'refunds_enabled', 'send_billing', 'send_shipping'] as $key) {
            $rules[$key] = ['required', 'boolean'];
        }
        $data = $request->validate($rules + [
            'merchant_id' => ['nullable', 'string', 'regex:/^[1-9][0-9]{0,19}$/D', Rule::requiredIf($request->boolean('enabled'))],
            'label' => ['required', 'string', 'max:60'],
            'currencies' => ['required', 'array', 'min:1', 'max:5'],
            'currencies.*' => ['required', 'string', 'distinct', Rule::in(CCAvenueSettings::CURRENCIES)],
            'language' => ['required', Rule::in(['EN', 'HI'])],
            'session_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'access_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{8,255}$/D', Rule::requiredIf($request->boolean('enabled') && $settings->secret('access_code') === null)],
            'working_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{8,255}$/D', Rule::requiredIf($request->boolean('enabled') && $settings->secret('working_key') === null)],
        ]);
        $data['merchant_id'] ??= '';
        $settings->update($data);
        $audit->record('ccavenue.settings_updated', null, [
            'enabled' => $data['enabled'], 'test_mode' => $data['test_mode'],
            'refunds_enabled' => $data['refunds_enabled'],
            'credentials_changed' => ! empty($data['access_code']) || ! empty($data['working_key']),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'CCAvenue settings saved.']);

        return redirect()->route('admin.modrixcommerce.ccavenue.index');
    }
}
