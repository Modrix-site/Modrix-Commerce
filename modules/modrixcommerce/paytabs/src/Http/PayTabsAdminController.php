<?php

namespace ModrixCommerce\PayTabs\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ModrixCommerce\PayTabs\PayTabsGateway;
use ModrixCommerce\PayTabs\PayTabsSettings;

class PayTabsAdminController extends Controller
{
    public function index(PayTabsSettings $settings)
    {
        return Inertia::render('module/modrixcommerce/paytabs/settings', [
            'settings' => $settings->toArray(), 'regions' => array_map(fn ($r) => $r[0], PayTabsSettings::REGIONS),
            'currencies' => PayTabsSettings::CURRENCIES, 'methods' => PayTabsSettings::METHODS,
            'updateUrl' => route('admin.modrixcommerce.paytabs.update'), 'webhookUrl' => route('webhooks.payments', PayTabsGateway::CODE),
            'reconcileUrl' => route('admin.modrixcommerce.paytabs.refunds.reconcile'),
        ]);
    }

    public function update(Request $request, PayTabsSettings $settings, AuditLogger $audit)
    {
        $rules = [];
        foreach (['enabled', 'test_mode', 'callbacks_enabled', 'refunds_enabled', 'send_billing', 'send_shipping', 'hide_shipping', 'all_methods'] as $key) {
            $rules[$key] = ['required', 'boolean'];
        }
        foreach (['profile_id' => false, 'test_profile_id' => true] as $key => $test) {
            $rules[$key] = ['nullable', 'string', 'regex:/^[1-9][0-9]{0,11}$/D', Rule::requiredIf($request->boolean('enabled') && $request->boolean('test_mode') === $test)];
        }
        foreach (['server_key' => false, 'test_server_key' => true] as $key => $test) {
            $rules[$key] = ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{8,255}$/D', Rule::requiredIf($request->boolean('enabled') && $request->boolean('test_mode') === $test && $settings->serverKey($test) === null)];
        }
        $data = $request->validate($rules + [
            'region' => ['required', Rule::in(array_keys(PayTabsSettings::REGIONS))],
            'label' => ['required', 'string', 'max:60'], 'language' => ['required', Rule::in(['en', 'ar'])],
            'session_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'currencies' => ['required', 'array', 'min:1', 'max:13'],
            'currencies.*' => ['required', 'string', 'distinct', Rule::in(PayTabsSettings::CURRENCIES)],
            'payment_methods' => ['present', 'array', Rule::when(! $request->boolean('all_methods'), ['min:1']), 'max:21'],
            'payment_methods.*' => ['required', 'string', 'distinct', Rule::in(PayTabsSettings::METHODS)],
        ]);
        $data['profile_id'] ??= '';
        $data['test_profile_id'] ??= '';
        $settings->update($data);
        $audit->record('paytabs.settings_updated', null, [
            'enabled' => $data['enabled'], 'test_mode' => $data['test_mode'], 'region' => $data['region'],
            'refunds_enabled' => $data['refunds_enabled'], 'credentials_changed' => ! empty($data['server_key']) || ! empty($data['test_server_key']),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'PayTabs settings saved.']);

        return redirect()->route('admin.modrixcommerce.paytabs.index');
    }

    public function reconcileRefund(Request $request, PayTabsGateway $gateway, PaymentReconciler $reconciler, AuditLogger $audit)
    {
        $data = $request->validate(['payment_id' => ['required', 'integer', 'min:1'], 'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/D']]);
        $payment = Payment::query()->where('provider', PayTabsGateway::CODE)->findOrFail($data['payment_id']);
        try {
            $update = $gateway->reconcileRefund($payment, $data['reference']);
            $reconciler->applyRefund($gateway, $update);
            $audit->record('paytabs.refund_reconciled', $payment->order, ['reference' => $update->reference, 'status' => $update->status]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'PayTabs refund status verified and applied.']);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.paytabs.index');
    }
}
