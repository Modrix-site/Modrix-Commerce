<?php

namespace ModrixCommerce\Sms\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use ModrixCommerce\Sms\Models\SmsMessageLog;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\PhoneNumber;
use ModrixCommerce\Sms\Providers\CustomHttpProvider;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsProviders;
use ModrixCommerce\Sms\SmsSender;
use ModrixCommerce\Sms\SmsSettings;

class SmsAdminController extends Controller
{
    public function index(SmsSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/sms/admin/index', [
            'settings' => $settings->toArray(),
            'placeholders' => ['store', 'name', 'order_number', 'total', 'order_url', 'tracking', 'tracking_number'],
            'stats' => [
                'verified' => SmsPhone::query()->whereNotNull('verified_at')->count(),
                'awaiting' => SmsPhone::query()->whereNull('verified_at')->count(),
                'sent_30d' => SmsMessageLog::query()->where('status', 'sent')->where('created_at', '>=', now()->subDays(30))->count(),
                'failed_30d' => SmsMessageLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'messages' => SmsMessageLog::query()->latest('id')->limit(50)->get()->map(fn (SmsMessageLog $m) => [
                'id' => $m->id,
                'to' => PhoneNumber::mask($m->to),
                'purpose' => $m->purpose,
                'event' => $m->event,
                'body' => $m->body,
                'provider' => $m->provider,
                'status' => $m->status,
                'error' => $m->error,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'urls' => [
                'update' => route('admin.modrixcommerce.sms.update'),
                'test' => route('admin.modrixcommerce.sms.test'),
            ],
        ]);
    }

    public function update(Request $request, SmsSettings $settings, SmsProviders $providers, AuditLogger $audit): RedirectResponse
    {
        $provider = $providers->find((string) $request->input('provider'));
        $rules = [
            'provider' => ['required', 'string', Rule::in(array_keys($providers->all()))],
            'default_country_code' => ['required', 'string', 'regex:/^\+?[1-9]\d{0,3}$/'],
            'require_at_registration' => ['required', 'boolean'],
            'code_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'verification_template' => ['required', 'string', 'max:300', 'regex:/\{code\}/'],
            'events' => ['required', 'array'],
            'config' => ['array'],
        ];

        foreach (array_keys(SmsSettings::EVENTS) as $event) {
            $rules["events.{$event}.enabled"] = ['required', 'boolean'];
            $rules["events.{$event}.template"] = ['required', 'string', 'max:480'];
        }

        foreach ($provider?->fields() ?? [] as $field) {
            $type = $field['type'] ?? 'string';
            $saved = $type === 'secret' && $settings->config($provider)[$field['key']] !== '';
            $rules["config.{$field['key']}"] = array_filter([
                ($field['required'] ?? false) && ! $saved ? 'required' : 'nullable',
                'string',
                $type === 'text' ? 'max:5000' : 'max:500',
                $type === 'select' ? Rule::in(array_column($field['options'] ?? [], 'value')) : null,
            ]);
        }

        $data = $request->validate($rules, [
            'verification_template.regex' => 'The verification message must contain {code}.',
        ]);
        $data['default_country_code'] = ltrim($data['default_country_code'], '+');

        if ($provider instanceof CustomHttpProvider) {
            $url = (string) ($data['config']['url'] ?? '');
            $problem = CustomHttpProvider::urlProblem(str_replace(['{', '}'], '', $url));
            if ($problem !== null) {
                return back()->withErrors(['config.url' => $problem]);
            }
        }

        $settings->update($data);
        $audit->record('sms.settings_updated', null, [
            'provider' => $data['provider'],
            'require_at_registration' => $data['require_at_registration'],
            'credentials_changed' => collect($provider?->fields() ?? [])
                ->contains(fn (array $f) => ($f['type'] ?? '') === 'secret' && ($data['config'][$f['key']] ?? '') !== ''),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'SMS settings saved.']);

        return redirect()->route('admin.modrixcommerce.sms.index');
    }

    public function test(Request $request, SmsSettings $settings, SmsSender $sender, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'string', 'max:30']]);
        $to = PhoneNumber::normalize($data['to'], (string) $settings->value('default_country_code'));

        if ($to === null) {
            return back()->withErrors(['to' => 'Enter a valid mobile number.']);
        }

        $result = $sender->send(
            new SmsMessage($to, 'Test message from '.config('app.name').': SMS is set up.', SmsMessage::PURPOSE_TEST),
            ['user_id' => $request->user()?->id],
        );
        $audit->record('sms.test_sent', null, ['to' => PhoneNumber::mask($to), 'sent' => $result->sent]);

        if (! $result->sent) {
            return back()->withErrors(['to' => $result->error ?? 'The test message was not sent.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Test message sent.']);

        return back();
    }
}
