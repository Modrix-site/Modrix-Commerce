<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\MailSettings;
use App\Core\Settings\StoreSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Settings → General: branding, email, maintenance, security, social links
 * and custom scripts. Each tab saves on its own and is audited; secrets
 * (the SMTP password) never leave the server.
 */
class GeneralSettingsController extends Controller
{
    public function __construct(
        private readonly GeneralSettings $general,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request, StoreSettings $store): Response
    {
        $user = $request->user();
        $mailPassword = $this->general->mailPassword();

        return Inertia::render('admin/settings/general', [
            'branding' => [
                ...$this->general->branding(),
                'urls' => $this->general->brandingProps($store->name()),
            ],
            'mail' => [...$this->general->mail(), 'password_unreadable' => $mailPassword === false, 'env_from' => (string) config('mail.from.address')],
            'maintenance' => $this->general->maintenance(),
            'security' => $this->general->security(),
            'social' => $this->general->social(),
            'scripts' => $this->general->scripts(),
            'canAdvanced' => $user instanceof User && $user->can('settings.advanced'),
            'hasTwoFactor' => $user instanceof User && $user->two_factor_confirmed_at !== null,
            'currentIp' => (string) $request->ip(),
            'defaultSessionLifetime' => (int) config('session.lifetime_default', config('session.lifetime')),
        ]);
    }

    public function branding(Request $request): RedirectResponse
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'];
        $data = $request->validate([
            'panel_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'accent' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'login_message' => ['sometimes', 'nullable', 'string', 'max:200'],
            'logo' => $image,
            'logo_dark' => $image,
            'icon' => $image,
            'login_logo' => $image,
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
            'remove' => ['sometimes', 'array'],
            'remove.*' => [Rule::in(GeneralSettings::BRANDING_IMAGES)],
        ]);

        $before = $this->general->branding();
        $after = $before;
        foreach (['panel_name', 'accent', 'login_message'] as $key) {
            if (array_key_exists($key, $data)) {
                $after[$key] = $data[$key] === null ? ($key === 'accent' ? null : '') : (string) $data[$key];
            }
        }

        $disk = Storage::disk($this->general->disk());
        foreach (GeneralSettings::BRANDING_IMAGES as $key) {
            $file = $request->file($key);
            $remove = in_array($key, $data['remove'] ?? [], true);
            if ($file === null && ! $remove) {
                continue;
            }
            if ($before[$key] !== null) {
                $disk->delete($before[$key]);
            }
            $after[$key] = $file !== null ? (string) $disk->putFile('branding', $file, 'public') : null;
        }

        $this->general->saveBranding($after);
        $this->record('branding', $before, $after);

        return $this->saved('Branding saved.');
    }

    public function mail(Request $request, MailSettings $mail): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', Rule::in(['env', 'smtp'])],
            'host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['required_if:driver,smtp', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'remove_password' => ['sometimes', 'boolean'],
            'from_address' => ['required_if:driver,smtp', 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        $before = $this->general->mail();
        $after = [
            'driver' => $data['driver'],
            'host' => (string) ($data['host'] ?? ''),
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => $data['encryption'],
            'username' => (string) ($data['username'] ?? ''),
            'from_address' => (string) ($data['from_address'] ?? ''),
            'from_name' => (string) ($data['from_name'] ?? ''),
            'reply_to' => (string) ($data['reply_to'] ?? ''),
        ];
        $this->general->saveMail($after);

        $passwordChange = null;
        if ($request->boolean('remove_password')) {
            $this->general->setMailPassword(null);
            $passwordChange = 'removed';
        } elseif (($data['password'] ?? '') !== '') {
            $this->general->setMailPassword((string) $data['password']);
            $passwordChange = 'changed';
        }

        unset($before['has_password']);
        $this->record('email', $before, $after, $passwordChange === null ? [] : ['password' => $passwordChange]);
        $mail->apply();

        return $this->saved('Email settings saved.');
    }

    public function testMail(Request $request, MailSettings $mail): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);
        $mail->apply();

        try {
            $mail->sendTest((string) $data['to']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['to' => 'The test email could not be sent: '.$mail->describeFailure($e)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Test email sent to {$data['to']}."]);

        return back();
    }

    public function maintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'headline' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'reopens_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'allowed_ips' => ['present', 'array', 'max:50'],
            'allowed_ips.*' => ['ip'],
        ]);

        $before = $this->general->maintenance();
        $after = [
            'enabled' => (bool) $data['enabled'],
            'headline' => (string) $data['headline'],
            'message' => (string) ($data['message'] ?? ''),
            'reopens_at' => (string) ($data['reopens_at'] ?? ''),
            'allowed_ips' => array_values(array_unique(array_map('strval', $data['allowed_ips']))),
        ];
        $this->general->save('maintenance', $after);
        $this->record('maintenance', $before, $after);

        return $this->saved($after['enabled'] ? 'Maintenance mode is on: the storefront is closed.' : 'Maintenance settings saved.');
    }

    public function security(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'require_two_factor' => ['required', 'boolean'],
            'session_lifetime' => ['nullable', 'integer', 'between:15,1440'],
            'login_attempts' => ['required', 'integer', 'between:3,20'],
            'password_min' => ['nullable', 'integer', 'between:8,64'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if ($data['require_two_factor'] && $user->two_factor_confirmed_at === null) {
            throw ValidationException::withMessages(['require_two_factor' => 'Turn on two-factor authentication for your own account first, or you would be locked out.']);
        }

        $before = $this->general->security();
        $after = [
            'require_two_factor' => (bool) $data['require_two_factor'],
            'session_lifetime' => isset($data['session_lifetime']) ? (int) $data['session_lifetime'] : null,
            'login_attempts' => (int) $data['login_attempts'],
            'password_min' => isset($data['password_min']) ? (int) $data['password_min'] : null,
        ];
        $this->general->save('security', $after);
        $this->record('security', $before, $after);

        return $this->saved('Security settings saved.');
    }

    public function social(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (GeneralSettings::SOCIAL as $key) {
            $rules[$key] = ['nullable', 'string', 'max:255', 'url:https'];
        }
        $data = $request->validate($rules);

        $before = $this->general->social();
        $after = array_combine(GeneralSettings::SOCIAL, array_map(fn (string $key) => (string) ($data[$key] ?? ''), GeneralSettings::SOCIAL));
        $this->general->save('social', $after);
        $this->record('social', $before, $after);

        return $this->saved('Social links saved.');
    }

    public function scripts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'head_enabled' => ['required', 'boolean'],
            'head' => ['nullable', 'string', 'max:10000'],
            'body_enabled' => ['required', 'boolean'],
            'body' => ['nullable', 'string', 'max:10000'],
            'on_checkout' => ['required', 'boolean'],
            'hosts' => ['present', 'array', 'max:20'],
            'hosts.*' => ['string', 'max:200', 'regex:/^https:\/\/[A-Za-z0-9*][A-Za-z0-9.\-*]*(:\d{1,5})?$/'],
        ]);

        $before = $this->general->scripts();
        $after = [
            'head_enabled' => (bool) $data['head_enabled'],
            'head' => (string) ($data['head'] ?? ''),
            'body_enabled' => (bool) $data['body_enabled'],
            'body' => (string) ($data['body'] ?? ''),
            'on_checkout' => (bool) $data['on_checkout'],
            'hosts' => array_values(array_unique(array_map(fn ($host) => strtolower((string) $host), $data['hosts']))),
        ];
        $this->general->save('scripts', $after);
        $this->record('scripts', $before, $after);

        return $this->saved('Custom code saved.');
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $extra  already-safe change notes (e.g. "password" => "changed")
     */
    private function record(string $section, array $before, array $after, array $extra = []): void
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }
        foreach ($extra as $key => $note) {
            $changes[$key] = $note;
        }
        if ($changes !== []) {
            $this->audit->record('settings.general_updated', null, ['section' => $section, 'changes' => $changes]);
        }
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
