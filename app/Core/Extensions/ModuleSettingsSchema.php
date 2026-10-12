<?php

namespace App\Core\Extensions;

use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes a module's merchant settings from its declared schema.
 *
 * Settings are plain typed values (boolean, string, text, integer, select,
 * email). Nothing a merchant enters is ever evaluated as code, template or
 * validation rule; the rules below are built by the platform from the type.
 */
final class ModuleSettingsSchema
{
    /** @var array<string, Manifest|null> */
    private array $manifests = [];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Current values, falling back to declared defaults.
     *
     * @return array<string, mixed>
     */
    public function values(Manifest $manifest): array
    {
        $values = [];

        foreach ($manifest->settings as $field) {
            $stored = $this->settings->get("{$manifest->settingsPrefix()}.{$field['key']}");
            $values[$field['key']] = $stored ?? ($field['default'] ?? ($field['type'] === 'boolean' ? false : null));
        }

        return $values;
    }

    public function get(Manifest $manifest, string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $values = $this->values($manifest)) ? ($values[$key] ?? $default) : $default;
    }

    /**
     * For module code: a setting of an installed module by name, e.g.
     * `app(ModuleSettingsSchema::class)->of('acme/reviews', 'auto_approve')`.
     */
    public function of(string $module, string $key, mixed $default = null): mixed
    {
        $manifest = $this->manifests[$module] ??= app(ModuleRepository::class)->find($module)?->manifest();

        return $manifest === null ? $default : $this->get($manifest, $key, $default);
    }

    /**
     * Validates and stores submitted values.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the stored values
     *
     * @throws ValidationException
     */
    public function save(Manifest $manifest, array $input): array
    {
        $rules = [];
        $labels = [];

        foreach ($manifest->settings as $field) {
            $rules[$field['key']] = self::rules($field);
            $labels[$field['key']] = $field['label'];
        }

        $validated = Validator::make($input, $rules, [], $labels)->validate();
        $values = [];

        foreach ($manifest->settings as $field) {
            $values["{$manifest->settingsPrefix()}.{$field['key']}"] = self::coerce($field, $validated[$field['key']] ?? null);
        }

        $this->settings->setMany($values);

        return $this->values($manifest);
    }

    public function forget(Manifest $manifest): void
    {
        $keys = array_map(fn (array $field) => "{$manifest->settingsPrefix()}.{$field['key']}", $manifest->settings);
        $this->settings->forget($keys);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    public static function rules(array $field): array
    {
        $required = ($field['required'] ?? false) === true && $field['type'] !== 'boolean';
        $base = [$required ? 'required' : 'nullable'];

        return match ($field['type']) {
            'boolean' => ['nullable', 'boolean'],
            'integer' => [...$base, 'integer', ...(isset($field['min']) ? ["min:{$field['min']}"] : []), ...(isset($field['max']) ? ["max:{$field['max']}"] : [])],
            'email' => [...$base, 'string', 'email', 'max:191'],
            'select' => [...$base, Rule::in(array_map(fn (array $o) => (string) $o['value'], $field['options']))],
            'text' => [...$base, 'string', 'max:'.min(10000, (int) ($field['max_length'] ?? 2000))],
            default => [...$base, 'string', 'max:'.min(500, (int) ($field['max_length'] ?? 255))],
        };
    }

    /**
     * Normalises a value to the field's type, or null when it does not fit.
     *
     * @param  array<string, mixed>  $field
     */
    public static function coerce(array $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $field['type'] === 'boolean' ? false : null;
        }

        return match ($field['type']) {
            'boolean' => is_bool($value) ? $value : (in_array($value, [1, '1', 'true', 'on'], true) ? true : (in_array($value, [0, '0', 'false', 'off'], true) ? false : null)),
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value)) ? (int) $value : null,
            'select' => in_array((string) $value, array_map(fn (array $o) => (string) $o['value'], $field['options'] ?? []), true) ? (string) $value : null,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null,
            default => is_string($value) ? $value : null,
        };
    }
}
