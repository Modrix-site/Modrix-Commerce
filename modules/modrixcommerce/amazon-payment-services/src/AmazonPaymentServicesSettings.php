<?php

namespace EcoModular\AmazonPaymentServices;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Amazon Payment Services.
 *
 * Secret passphrases and access code are stored encrypted through SecretSettings
 * and are never exposed to client-side assets.
 */
final class AmazonPaymentServicesSettings
{
    public const SHA_REQUEST_PHRASE = 'aps.sha_request_phrase';

    public const SHA_RESPONSE_PHRASE = 'aps.sha_response_phrase';

    public const ACCESS_CODE = 'aps.access_code';

    public const COMMANDS = ['PURCHASE', 'AUTHORIZATION'];

    public const SHA_TYPES = ['sha256', 'sha512'];

    public const LANGUAGES = ['en', 'ar'];

    public const DEFAULT_CURRENCIES = ['AED', 'SAR', 'USD', 'EUR', 'GBP', 'EGP', 'QAR', 'KWD', 'BHD', 'OMR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('aps.enabled', false);
    }

    public function merchantIdentifier(): ?string
    {
        $id = $this->settings->get('aps.merchant_identifier');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function accessCode(): ?string
    {
        return $this->secrets->get(self::ACCESS_CODE);
    }

    public function shaRequestPhrase(): ?string
    {
        return $this->secrets->get(self::SHA_REQUEST_PHRASE);
    }

    public function shaResponsePhrase(): ?string
    {
        return $this->secrets->get(self::SHA_RESPONSE_PHRASE);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('aps.test_mode', true);
    }

    public function shaType(): string
    {
        $type = $this->settings->get('aps.sha_type', 'sha256');

        return is_string($type) && in_array(strtolower($type), self::SHA_TYPES, true)
            ? strtolower($type)
            : 'sha256';
    }

    public function command(): string
    {
        $cmd = $this->settings->get('aps.command', 'PURCHASE');

        return is_string($cmd) && in_array(strtoupper($cmd), self::COMMANDS, true)
            ? strtoupper($cmd)
            : 'PURCHASE';
    }

    public function language(): string
    {
        $lang = $this->settings->get('aps.language', 'en');

        return is_string($lang) && in_array(strtolower($lang), self::LANGUAGES, true)
            ? strtolower($lang)
            : 'en';
    }

    public function label(): string
    {
        $label = $this->settings->get('aps.label');

        return is_string($label) && trim($label) !== '' ? trim($label) : 'Amazon Payment Services';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $currencies = $this->settings->get('aps.currencies');

        if (is_array($currencies) && $currencies !== []) {
            return array_values(array_filter($currencies, fn ($c) => is_string($c) && strlen($c) === 3));
        }

        return self::DEFAULT_CURRENCIES;
    }

    public function hasCredentials(): bool
    {
        return $this->merchantIdentifier() !== null
            && $this->accessCode() !== null
            && $this->shaRequestPhrase() !== null
            && $this->shaResponsePhrase() !== null;
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    public function update(
        bool $enabled,
        string $merchantIdentifier,
        bool $testMode,
        string $shaType,
        string $command,
        string $language,
        string $label,
        array $currencies,
        ?string $accessCode = null,
        ?string $shaRequestPhrase = null,
        ?string $shaResponsePhrase = null,
    ): void {
        $this->settings->set('aps.enabled', $enabled);
        $this->settings->set('aps.merchant_identifier', trim($merchantIdentifier));
        $this->settings->set('aps.test_mode', $testMode);
        $this->settings->set('aps.sha_type', in_array($shaType, self::SHA_TYPES, true) ? $shaType : 'sha256');
        $this->settings->set('aps.command', in_array($command, self::COMMANDS, true) ? $command : 'PURCHASE');
        $this->settings->set('aps.language', in_array($language, self::LANGUAGES, true) ? $language : 'en');
        $this->settings->set('aps.label', trim($label) !== '' ? trim($label) : 'Amazon Payment Services');
        $this->settings->set('aps.currencies', array_values(array_unique(array_map('strtoupper', $currencies))));

        if ($accessCode !== null && trim($accessCode) !== '') {
            $this->secrets->put(self::ACCESS_CODE, trim($accessCode));
        }

        if ($shaRequestPhrase !== null && trim($shaRequestPhrase) !== '') {
            $this->secrets->put(self::SHA_REQUEST_PHRASE, trim($shaRequestPhrase));
        }

        if ($shaResponsePhrase !== null && trim($shaResponsePhrase) !== '') {
            $this->secrets->put(self::SHA_RESPONSE_PHRASE, trim($shaResponsePhrase));
        }
    }

    /**
     * @return array<string, mixed> safe for Inertia props (no raw secrets)
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'merchant_identifier' => $this->merchantIdentifier() ?? '',
            'test_mode' => $this->testMode(),
            'sha_type' => $this->shaType(),
            'command' => $this->command(),
            'language' => $this->language(),
            'label' => $this->label(),
            'currencies' => $this->currencies(),
            'access_code_hint' => $this->accessCode() !== null ? '••••••••' : null,
            'sha_request_phrase_hint' => $this->shaRequestPhrase() !== null ? '••••••••' : null,
            'sha_response_phrase_hint' => $this->shaResponsePhrase() !== null ? '••••••••' : null,
            'ready' => $this->ready(),
        ];
    }
}
