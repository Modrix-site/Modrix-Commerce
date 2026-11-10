<?php

namespace ModrixCommerce\Razorpay\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ModrixCommerce\Razorpay\RazorpayGateway;
use ModrixCommerce\Razorpay\RazorpaySettings;

class RazorpayAdminController extends Controller
{
    public function index(RazorpaySettings $settings)
    {
        return Inertia::render('module/modrixcommerce/razorpay/settings', [
            'settings' => $settings->toArray(), 'currencies' => RazorpaySettings::CURRENCIES, 'methods' => RazorpaySettings::METHODS,
            'updateUrl' => route('admin.modrixcommerce.razorpay.update'), 'webhookUrl' => route('webhooks.payments', RazorpayGateway::CODE),
            'reconcileUrl' => route('admin.modrixcommerce.razorpay.refunds.reconcile'),
        ]);
    }

    public function update(Request $request, RazorpaySettings $settings, AuditLogger $audit)
    {
        $rules = [];
        foreach (['enabled', 'test_mode', 'webhooks_enabled', 'refunds_enabled', 'capture_authorized', 'prefill_customer', 'retry_enabled', 'confirm_close', 'auto_open', 'all_methods'] as $key) {
            $rules[$key] = ['required', 'boolean'];
        }
        foreach (['key_id' => false, 'test_key_id' => true] as $key => $test) {
            $rules[$key] = ['nullable', 'string', 'regex:/^rzp_'.($test ? 'test' : 'live').'_[A-Za-z0-9]{6,48}$/D', Rule::requiredIf($request->boolean('enabled') && $request->boolean('test_mode') === $test)];
        }
        foreach (['key_secret' => false, 'test_key_secret' => true, 'webhook_secret' => false, 'test_webhook_secret' => true] as $key => $test) {
            $kind = str_contains($key, 'webhook') ? 'webhook_secret' : 'key_secret';
            $required = $request->boolean('enabled') && $request->boolean('test_mode') === $test && ($kind === 'key_secret' || $request->boolean('webhooks_enabled'));
            $rules[$key] = ['nullable', 'string', 'min:8', 'max:255', Rule::requiredIf($required && $settings->secret($kind, $test) === null)];
        }
        $data = $request->validate($rules + [
            'label' => ['required', 'string', 'max:60'], 'brand_name' => ['required', 'string', 'max:80'],
            'theme_color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/D'],
            'session_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'currencies' => ['required', 'array', 'min:1', 'max:13'],
            'currencies.*' => ['required', 'string', 'distinct', Rule::in(RazorpaySettings::CURRENCIES)],
            'payment_methods' => ['present', 'array', Rule::when(! $request->boolean('all_methods'), ['min:1']), 'max:6'],
            'payment_methods.*' => ['required', 'string', 'distinct', Rule::in(RazorpaySettings::METHODS)],
        ]);
        $data['key_id'] ??= '';
        $data['test_key_id'] ??= '';
        $settings->update($data);
        $audit->record('razorpay.settings_updated', null, [
            'enabled' => $data['enabled'], 'test_mode' => $data['test_mode'], 'capture_authorized' => $data['capture_authorized'],
            'refunds_enabled' => $data['refunds_enabled'],
            'credentials_changed' => ! empty($data['key_secret']) || ! empty($data['test_key_secret']) || ! empty($data['webhook_secret']) || ! empty($data['test_webhook_secret']),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Razorpay settings saved.']);

        return redirect()->route('admin.modrixcommerce.razorpay.index');
    }

    public function reconcileRefund(Request $request, RazorpayGateway $gateway, PaymentReconciler $reconciler, AuditLogger $audit)
    {
        $data = $request->validate(['payment_id' => ['required', 'integer', 'min:1'], 'reference' => ['required', 'string', 'regex:/^rfnd_[A-Za-z0-9]{1,59}$/D']]);
        $payment = Payment::query()->where('provider', RazorpayGateway::CODE)->findOrFail($data['payment_id']);
        try {
            $update = $gateway->reconcileRefund($payment, $data['reference']);
            $reconciler->applyRefund($gateway, $update);
            $audit->record('razorpay.refund_reconciled', $payment->order, ['reference' => $update->reference, 'status' => $update->status]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Razorpay refund verified and applied to the order.']);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.modrixcommerce.razorpay.index');
    }
}
