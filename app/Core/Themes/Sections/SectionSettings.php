<?php

namespace App\Core\Themes\Sections;

use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\Models\ProductTag;

/**
 * Setting types of section and block schemas (sections v2) and how values
 * are validated. Values are data only: they are checked against the type and
 * normalised, never evaluated. Checks reuse ThemeSchema::check so a colour,
 * link or image is validated exactly like a theme design setting.
 */
final class SectionSettings
{
    /** Types that hold a value. "header" is a label in the editor only. */
    public const VALUE_TYPES = ['text', 'textarea', 'richtext', 'number', 'range', 'checkbox', 'select', 'radio', 'color', 'image', 'url', 'collection', 'product', 'tags'];

    public const TYPES = [...self::VALUE_TYPES, 'header'];

    private const SLUG = '/^[a-z0-9][a-z0-9-]{0,190}$/';

    /**
     * Problems with one setting definition, as messages.
     *
     * @param  array<string, mixed>  $setting
     * @return list<string>
     */
    public static function definitionErrors(array $setting): array
    {
        $type = $setting['type'] ?? null;
        $errors = [];

        if (! in_array($type, self::TYPES, true)) {
            return ['type must be one of '.implode(', ', self::TYPES)];
        }

        if ($type === 'header') {
            return is_string($setting['content'] ?? null) && $setting['content'] !== '' ? [] : ['a header needs "content"'];
        }

        if (! is_string($setting['id'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $setting['id'])) {
            $errors[] = 'id must be lowercase snake_case';
        }

        if (! is_string($setting['label'] ?? null) || $setting['label'] === '' || mb_strlen($setting['label']) > 120) {
            $errors[] = 'label is required (max 120)';
        }

        if (in_array($type, ['select', 'radio'], true)) {
            $options = $setting['options'] ?? null;

            if (! is_array($options) || $options === [] || count($options) > 50
                || array_filter($options, fn ($o) => ! is_array($o) || ! is_string($o['value'] ?? null) || ! is_string($o['label'] ?? null)) !== []) {
                $errors[] = 'options must list 1-50 {"value", "label"} pairs';
            }
        }

        if (in_array($type, ['number', 'range'], true)) {
            foreach (['min', 'max', 'step'] as $key) {
                if (isset($setting[$key]) && ! is_int($setting[$key])) {
                    $errors[] = "{$key} must be an integer";
                }
            }

            if ($type === 'range' && (! isset($setting['min'], $setting['max']) || $setting['min'] >= $setting['max'])) {
                $errors[] = 'a range needs min < max';
            }
        }

        if ($errors === [] && array_key_exists('default', $setting)) {
            [, $error] = self::check($setting, $setting['default'], fn () => true);

            if ($error !== null) {
                $errors[] = "default {$error}";
            }
        }

        return $errors;
    }

    /**
     * Validates and normalises one value.
     *
     * @param  array<string, mixed>  $setting
     * @param  callable(string): bool  $assetExists
     * @return array{0: mixed, 1: string|null}
     */
    public static function check(array $setting, mixed $value, callable $assetExists): array
    {
        $type = (string) $setting['type'];

        if ($type === 'tags') {
            return self::checkTags($value);
        }

        if (in_array($type, ['collection', 'product'], true)) {
            if ($value === null || $value === '') {
                return ['', null];
            }

            return is_string($value) && preg_match(self::SLUG, $value) ? [$value, null] : [null, "must be a {$type} handle"];
        }

        $field = match ($type) {
            'text' => ['type' => 'string', 'max' => (int) ($setting['max_length'] ?? 500)],
            'textarea', 'richtext' => ['type' => 'text', 'max' => (int) ($setting['max_length'] ?? 5000)],
            'number', 'range' => ['type' => 'integer', 'min' => $setting['min'] ?? null, 'max' => $setting['max'] ?? null],
            'checkbox' => ['type' => 'boolean'],
            // Malformed options (e.g. plain strings) are reported by the schema check;
            // reading them here must not crash, or one bad theme breaks the themes page.
            'select', 'radio' => ['type' => 'select', 'options' => array_map(fn (mixed $o) => is_array($o) ? (string) ($o['value'] ?? '') : (is_scalar($o) ? (string) $o : ''), (array) ($setting['options'] ?? []))],
            'url' => ['type' => 'link'],
            default => ['type' => $type],
        };

        $field = array_filter($field, fn ($v) => $v !== null);

        return ThemeSchema::check($field, $value, $assetExists);
    }

    /**
     * Product tags: a list (or a comma-separated string) of up to 24 tags,
     * normalised like product tags. Empty means the setting's fallback.
     *
     * @return array{0: list<string>|null, 1: string|null}
     */
    private static function checkTags(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [[], null];
        }

        $items = is_string($value) ? explode(',', $value) : $value;

        if (! is_array($items) || array_filter($items, fn ($t) => ! is_string($t)) !== []) {
            return [null, 'must be a list of tags'];
        }

        $tags = array_values(array_unique(array_filter(array_map(ProductTag::normalise(...), $items), fn (string $t) => $t !== '')));

        if (count($tags) > 24 || array_filter($tags, fn (string $t) => mb_strlen($t) > ProductTag::MAX_LENGTH) !== []) {
            return [null, 'must list at most 24 tags of up to '.ProductTag::MAX_LENGTH.' characters'];
        }

        return [$tags, null];
    }

    /**
     * The default value of a setting (Shopify's: the schema default, or empty).
     *
     * @param  array<string, mixed>  $setting
     */
    public static function default(array $setting): mixed
    {
        if (array_key_exists('default', $setting)) {
            return $setting['default'];
        }

        return match ($setting['type']) {
            'checkbox' => false,
            'tags' => [],
            'number', 'range' => $setting['min'] ?? 0,
            'select', 'radio' => is_array($setting['options'][0] ?? null) ? (string) ($setting['options'][0]['value'] ?? '') : '',
            default => '',
        };
    }

    /**
     * Value-holding settings keyed by id.
     *
     * @param  list<array<string, mixed>>  $settings
     * @return array<string, array<string, mixed>>
     */
    public static function byId(array $settings): array
    {
        $byId = [];

        foreach ($settings as $setting) {
            if (($setting['type'] ?? null) !== 'header' && is_string($setting['id'] ?? null)) {
                $byId[$setting['id']] = $setting;
            }
        }

        return $byId;
    }
}
