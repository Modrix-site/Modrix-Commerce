<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Marketplace\Models\Setting;
use Marketplace\Services\SigningKey;
use Marketplace\Services\StripeGateway;
use Marketplace\Services\Uploads;
use Throwable;

/**
 * Settings tabs: general, payments (Stripe), email (SMTP) and licensing.
 * Secrets are write-only: a blank field keeps the stored value and pages
 * only show whether one is set.
 */
class SettingsController
{
    public const TABS = ['general' => 'General', 'payments' => 'Payments', 'email' => 'Email', 'licensing' => 'Licensing'];

    public function show(Request $request, SigningKey $key, StripeGateway $stripe): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'general';

        return view('admin.settings', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'general' => [
                'catalog_name' => (string) Setting::get('catalog_name', ''),
                'tagline' => (string) Setting::get('tagline', ''),
                'currency' => (string) (Setting::get('currency') ?: config('marketplace.currency')),
                'support_email' => (string) Setting::get('support_email', ''),
                'logo' => (bool) Setting::get('logo'),
            ],
            'stripe' => [
                'publishable' => (string) Setting::get('stripe_publishable', ''),
                'secret_set' => (string) Setting::get('stripe_secret', '') !== '',
                'webhook_set' => (string) Setting::get('stripe_webhook_secret', '') !== '',
                'configured' => $stripe->configured(),
                'webhook_url' => url('/stripe/webhook'),
            ],
            'smtp' => [
                'host' => (string) Setting::get('smtp_host', ''),
                'port' => (string) Setting::get('smtp_port', '587'),
                'encryption' => (string) Setting::get('smtp_encryption', 'tls'),
                'username' => (string) Setting::get('smtp_username', ''),
                'password_set' => (string) Setting::get('smtp_password', '') !== '',
                'from' => (string) Setting::get('mail_from', ''),
                'from_name' => (string) Setting::get('mail_from_name', ''),
            ],
            'signing' => ['public_key' => $key->publicKey(), 'key_id' => $key->keyId()],
        ]);
    }

    public function saveGeneral(Request $request, Uploads $uploads): RedirectResponse
    {
        $data = $request->validate([
            'catalog_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'logo' => ['nullable', 'file'],
        ]);

        Setting::put('catalog_name', $data['catalog_name']);
        Setting::put('tagline', (string) ($data['tagline'] ?? ''));
        Setting::put('currency', strtoupper($data['currency']));
        Setting::put('support_email', (string) ($data['support_email'] ?? ''));

        if ($request->hasFile('logo')) {
            Setting::put('logo', $uploads->image($request->file('logo'), 'branding', 'logo'));
        } elseif ($request->boolean('remove_logo')) {
            Setting::forget('logo');
        }

        return redirect('/admin/general?tab=general')->with('notice', 'Settings saved.');
    }

    public function saveStripe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stripe_publishable' => ['nullable', 'string', 'max:255', 'regex:/^pk_(test|live)_/'],
            'stripe_secret' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_/'],
        ]);

        Setting::put('stripe_publishable', (string) ($data['stripe_publishable'] ?? ''));

        foreach (['stripe_secret', 'stripe_webhook_secret'] as $field) {
            if (($data[$field] ?? '') !== '') {
                Setting::put($field, $data[$field], secret: true);
            }
        }

        return redirect('/admin/general?tab=payments')->with('notice', 'Stripe settings saved.');
    }

    public function saveSmtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'mail_from' => ['nullable', 'email', 'max:191'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        Setting::put('smtp_host', (string) ($data['smtp_host'] ?? ''));
        Setting::put('smtp_port', (int) ($data['smtp_port'] ?? 587));
        Setting::put('smtp_encryption', (string) ($data['smtp_encryption'] ?? 'tls'));
        Setting::put('smtp_username', (string) ($data['smtp_username'] ?? ''));
        Setting::put('mail_from', (string) ($data['mail_from'] ?? ''));
        Setting::put('mail_from_name', (string) ($data['mail_from_name'] ?? ''));

        if (($data['smtp_password'] ?? '') !== '') {
            Setting::put('smtp_password', $data['smtp_password'], secret: true);
        }

        return redirect('/admin/general?tab=email')->with('notice', 'Email settings saved.');
    }

    /** Sends a test message to the signed-in admin. */
    public function testSmtp(Request $request): RedirectResponse
    {
        try {
            Mail::raw('This is a test message from your marketplace. Email delivery works.', function ($message) use ($request) {
                $message->to($request->user()->email)->subject('Marketplace email test');
            });
        } catch (Throwable $e) {
            return redirect('/admin/general?tab=email')->withErrors(['smtp_host' => 'The test email could not be sent: '.$e->getMessage()]);
        }

        return redirect('/admin/general?tab=email')->with('notice', 'Test email sent to '.$request->user()->email.'.');
    }
}
