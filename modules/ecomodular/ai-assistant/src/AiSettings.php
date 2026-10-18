<?php

namespace EcoModular\AiAssistant;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use Illuminate\Validation\ValidationException;

final class AiSettings
{
    public function __construct(private readonly ModuleSettingsSchema $settings, private readonly SecretSettings $secrets) {}

    public function enabled(): bool
    {
        return (bool) $this->get('enabled', true);
    }

    /** Supported providers and the API modes each accepts (first is the default). */
    public const PROVIDERS = [
        'openai' => ['name' => 'OpenAI', 'model' => 'gpt-6-luna', 'modes' => ['responses', 'chat', 'legacy_chat']],
        'kimi' => ['name' => 'Kimi', 'model' => 'kimi-k3', 'modes' => ['legacy_chat', 'chat']],
        'deepseek' => ['name' => 'DeepSeek', 'model' => 'deepseek-flash', 'modes' => ['legacy_chat', 'chat']],
    ];

    public function provider(): string
    {
        $provider = (string) $this->get('provider', 'openai');
        if (! array_key_exists($provider, self::PROVIDERS)) {
            throw ValidationException::withMessages(['ai' => 'Select OpenAI, Kimi or DeepSeek in AI Assistant settings.']);
        }

        return $provider;
    }

    public static function secretName(string $provider): string
    {
        return 'ai-assistant.'.$provider;
    }

    public function apiKey(): string
    {
        $key = $this->secrets->get(self::secretName($this->provider()));
        if ($key === null) {
            throw ValidationException::withMessages(['ai' => 'Add an API key for the selected provider in AI Assistant settings.']);
        }

        return $key;
    }

    public function model(): string
    {
        $model = (string) $this->get($this->provider().'_model', self::PROVIDERS[$this->provider()]['model']);
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,199}$/', $model)) {
            throw ValidationException::withMessages(['ai' => 'Enter a valid model ID in AI Assistant settings.']);
        }

        return $model;
    }

    public function baseUrl(): string
    {
        return match ($this->provider()) {
            'openai' => 'https://api.openai.com/v1',
            'deepseek' => 'https://api.deepseek.com',
            default => $this->get('kimi_region', 'international') === 'china' ? 'https://api.moonshot.cn/v1' : 'https://api.moonshot.ai/v1',
        };
    }

    public function mode(): string
    {
        $modes = self::PROVIDERS[$this->provider()]['modes'];
        $mode = (string) $this->get($this->provider().'_api', $modes[0]);
        if (! in_array($mode, $modes, true)) {
            throw ValidationException::withMessages(['ai' => 'Select a supported API mode in AI Assistant settings.']);
        }

        return $mode;
    }

    public function jsonMode(): bool
    {
        return (bool) $this->get('json_mode', true);
    }

    public function maxTokens(): int
    {
        return max(256, min(32768, (int) $this->get('max_tokens', 8192)));
    }

    public function timeout(): int
    {
        return max(10, min(120, (int) $this->get('timeout', 90)));
    }

    public function tone(): string
    {
        $tone = (string) $this->get('tone', 'professional');

        return in_array($tone, ['professional', 'friendly', 'concise'], true) ? $tone : 'professional';
    }

    private function get(string $key, mixed $default): mixed
    {
        return $this->settings->of(AiAssistantServiceProvider::MODULE, $key, $default);
    }
}
