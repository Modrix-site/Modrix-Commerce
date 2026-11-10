<?php

namespace App\Core\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → General: admin branding, outgoing email (SMTP), maintenance
 * mode, security and social links / custom scripts. Stored in the settings
 * store under general.*; the defaults reproduce the platform's behaviour
 * before anything is saved.
 */
final class GeneralSettings
{
    public const BRANDING_IMAGES = ['logo', 'logo_dark', 'icon', 'favicon', 'login_logo'];

    public const SOCIAL = ['facebook', 'instagram', 'x', 'tiktok', 'youtube', 'linkedin', 'pinterest', 'whatsapp'];

    private const PASSWORD_KEY = 'general.mail.password_encrypted';

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array{accent: string|null, login_message: string, logo: string|null, logo_dark: string|null, icon: string|null, favicon: string|null, login_logo: string|null}
     */
    public function branding(): array
    {
        $saved = (array) $this->settings->get('general.branding', []);

        return [
            'accent' => isset($saved['accent']) ? (string) $saved['accent'] : null,
            'login_message' => (string) ($saved['login_message'] ?? ''),
            ...array_combine(self::BRANDING_IMAGES, array_map(fn (string $key) => isset($saved[$key]) && $saved[$key] !== '' ? (string) $saved[$key] : null, self::BRANDING_IMAGES)),
        ];
    }

    /**
     * Branding for every page: texts and public image URLs.
     *
     * @return array<string, string|null>
     */
    public function brandingProps(string $storeName): array
    {
        $branding = $this->branding();
        $disk = Storage::disk($this->disk());
        $props = [
            // One name everywhere: the admin shows the store name (Settings → Store).
            'panel_name' => $storeName,
            'accent' => $branding['accent'],
            'login_message' => $branding['login_message'],
        ];
        foreach (self::BRANDING_IMAGES as $key) {
            $props[$key] = $branding[$key] !== null ? $disk->url($branding[$key]) : null;
        }

        return $props;
    }

    /** @param  array<string, mixed>  $branding */
    public function saveBranding(array $branding): void
    {
        $this->settings->set('general.branding', $branding);
    }

    public function disk(): string
    {
        return (string) config('ecomodular.media_disk', 'public');
    }

    /**
     * @return array{driver: string, host: string, port: int, encryption: string, username: string, from_address: string, from_name: string, reply_to: string, has_password: bool}
     */
    public function mail(): array
    {
        $saved = (array) $this->settings->get('general.mail', []);

        return [
            'driver' => ($saved['driver'] ?? 'env') === 'smtp' ? 'smtp' : 'env',
            'host' => (string) ($saved['host'] ?? ''),
            'port' => (int) ($saved['port'] ?? 587),
            'encryption' => in_array($saved['encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? (string) ($saved['encryption'] ?? 'tls') : 'tls',
            'username' => (string) ($saved['username'] ?? ''),
            'from_address' => (string) ($saved['from_address'] ?? ''),
            'from_name' => (string) ($saved['from_name'] ?? ''),
            'reply_to' => (string) ($saved['reply_to'] ?? ''),
            'has_password' => is_string($this->settings->get(self::PASSWORD_KEY)),
        ];
    }

    /** @param  array<string, mixed>  $mail  without the password */
    public function saveMail(array $mail): void
    {
        unset($mail['password'], $mail['has_password']);
        $this->settings->set('general.mail', $mail);
    }

    public function setMailPassword(?string $password): void
    {
        $this->settings->set(self::PASSWORD_KEY, $password === null ? null : Crypt::encryptString($password));
    }

    /**
     * The SMTP password, or null when none is saved. False when one is saved
     * but cannot be read (the app key changed).
     */
    public function mailPassword(): string|false|null
    {
        $stored = $this->settings->get(self::PASSWORD_KEY);

        if (! is_string($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * @return array{enabled: bool, headline: string, message: string, reopens_at: string, allowed_ips: list<string>}
     */
    public function maintenance(): array
    {
        $saved = (array) $this->settings->get('general.maintenance', []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'headline' => (string) ($saved['headline'] ?? 'We will be back soon'),
            'message' => (string) ($saved['message'] ?? 'Our store is closed for maintenance. Please come back shortly.'),
            'reopens_at' => (string) ($saved['reopens_at'] ?? ''),
            'allowed_ips' => array_values(array_map('strval', (array) ($saved['allowed_ips'] ?? []))),
        ];
    }

    /**
     * @return array{registration_enabled: bool, require_two_factor: bool, session_lifetime: int|null, login_attempts: int, password_min: int|null}
     */
    public function security(): array
    {
        $saved = (array) $this->settings->get('general.security', []);

        return [
            'registration_enabled' => (bool) ($saved['registration_enabled'] ?? true),
            'require_two_factor' => (bool) ($saved['require_two_factor'] ?? false),
            'session_lifetime' => isset($saved['session_lifetime']) ? (int) $saved['session_lifetime'] : null,
            'login_attempts' => (int) ($saved['login_attempts'] ?? 5),
            'password_min' => isset($saved['password_min']) ? (int) $saved['password_min'] : null,
        ];
    }

    public function registrationEnabled(): bool
    {
        return $this->security()['registration_enabled'];
    }

    /** @return array<string, string> */
    public function social(): array
    {
        $saved = (array) $this->settings->get('general.social', []);

        return array_combine(self::SOCIAL, array_map(fn (string $key) => (string) ($saved[$key] ?? ''), self::SOCIAL));
    }

    /**
     * @return array{head_enabled: bool, head: string, body_enabled: bool, body: string, on_checkout: bool, hosts: list<string>}
     */
    public function scripts(): array
    {
        $saved = (array) $this->settings->get('general.scripts', []);

        return [
            'head_enabled' => (bool) ($saved['head_enabled'] ?? false),
            'head' => (string) ($saved['head'] ?? ''),
            'body_enabled' => (bool) ($saved['body_enabled'] ?? false),
            'body' => (string) ($saved['body'] ?? ''),
            'on_checkout' => (bool) ($saved['on_checkout'] ?? false),
            'hosts' => array_values(array_map('strval', (array) ($saved['hosts'] ?? []))),
        ];
    }

    /**
     * Hosts the custom code may load from (added to the CSP), only while a
     * snippet is on.
     *
     * @return list<string>
     */
    public function scriptHosts(): array
    {
        $scripts = $this->scripts();

        return $scripts['head_enabled'] || $scripts['body_enabled'] ? $scripts['hosts'] : [];
    }

    /** @param  array<string, mixed>  $values */
    public function save(string $section, array $values): void
    {
        $this->settings->set("general.{$section}", $values);
    }
}
