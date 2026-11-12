<?php

namespace App\Core\Themes;

final class ThemeCustomizerSchema
{
    /** @return list<string> */
    public static function errors(mixed $schema, callable $assetExists): array
    {
        if (! is_array($schema) || array_diff(array_keys($schema), ['title', 'description', 'mode', 'core_fields', 'sections', 'hide_core_fields', 'fields', 'presets']) !== []) {
            return ['customizer must contain only title, description, mode, core_fields, sections, hide_core_fields, fields and presets.'];
        }
        $errors = [];
        if (! in_array($schema['mode'] ?? 'extend', ['extend', 'replace'], true)) {
            $errors[] = 'customizer.mode must be extend or replace.';
        }
        if (array_key_exists('sections', $schema) && ! is_bool($schema['sections'])) {
            $errors[] = 'customizer.sections must be a boolean.';
        }
        if (array_key_exists('core_fields', $schema)) {
            $core = $schema['core_fields'];
            if (! is_array($core) || ! array_is_list($core)) {
                $errors[] = 'customizer.core_fields must be a list of core field names.';
            } else {
                foreach ($core as $key) {
                    if (! is_string($key) || ! array_key_exists($key, ThemeSchema::designFields())) {
                        $errors[] = 'customizer.core_fields contains an unknown core field.';
                    }
                }
            }
        }
        foreach (['title' => 120, 'description' => 500] as $key => $max) {
            if (isset($schema[$key]) && (! is_string($schema[$key]) || mb_strlen($schema[$key]) > $max)) {
                $errors[] = "customizer.{$key} must be text of at most {$max} characters.";
            }
        }
        $hidden = $schema['hide_core_fields'] ?? [];
        if (! is_array($hidden) || ! array_is_list($hidden) || count($hidden) > 150) {
            $errors[] = 'customizer.hide_core_fields must be a list of core field names.';
        } else {
            foreach ($hidden as $key) {
                if (! is_string($key) || ! array_key_exists($key, ThemeSchema::designFields())) {
                    $errors[] = 'customizer.hide_core_fields contains an unknown core field.';
                }
            }
        }
        $fields = $schema['fields'] ?? [];
        if (! is_array($fields) || count($fields) > 160 || ($fields !== [] && array_is_list($fields))) {
            return [...$errors, 'customizer.fields must be an object with at most 160 fields.'];
        }
        foreach ($fields as $key => $field) {
            if (! preg_match('/^custom\.[a-z][a-z0-9_]{0,49}$/', (string) $key) || ! is_array($field)
                || array_diff(array_keys($field), ['type', 'label', 'group', 'tab', 'default', 'options', 'min', 'max']) !== []
                || ! in_array($field['type'] ?? null, ['string', 'text', 'color', 'boolean', 'integer', 'select', 'link', 'image'], true)
                || ! is_string($field['label'] ?? null) || mb_strlen($field['label']) > 120 || trim($field['label']) === ''
                || ! is_string($field['group'] ?? '') || mb_strlen($field['group'] ?? '') > 100 || ! array_key_exists('default', $field)) {
                $errors[] = "customizer.fields.{$key} has an invalid definition.";

                continue;
            }
            if (isset($field['min']) && (! is_int($field['min']) || $field['min'] < -100000 || $field['min'] > 100000)) {
                $errors[] = "{$key}.min must be an integer between -100000 and 100000.";

                continue;
            }
            if (isset($field['max']) && (! is_int($field['max']) || $field['max'] < 0 || $field['max'] > 100000 || $field['max'] < ($field['min'] ?? 0))) {
                $errors[] = "{$key}.max must be an integer between min and 100000.";

                continue;
            }
            if ($field['type'] === 'select') {
                $options = $field['options'] ?? null;
                if (! is_array($options) || ! array_is_list($options) || count($options) < 1 || count($options) > 30 || count(array_filter($options, fn ($v) => is_string($v) && $v !== '' && mb_strlen($v) <= 100)) !== count($options)) {
                    $errors[] = "{$key}.options must be a list of 1–30 text choices.";

                    continue;
                }
            }
            [, $error] = ThemeSchema::check($field + ['min' => 0, 'max' => 1000], $field['default'], $assetExists, []);
            if ($error !== null) {
                $errors[] = "{$key}.default {$error}.";
            }
        }
        $errors = [...$errors, ...self::presetErrors($schema['presets'] ?? [], $fields, $assetExists)];

        return $errors;
    }

    /**
     * Named palettes that set several design fields at once. A merchant who
     * applies one gets a coordinated look without editing every swatch; the
     * individual fields stay editable afterwards.
     *
     * @param  array<string, array<string, mixed>>  $customFields
     * @return list<string>
     */
    public static function presetErrors(mixed $presets, array $customFields, callable $assetExists): array
    {
        if ($presets === []) {
            return [];
        }

        if (! is_array($presets) || array_is_list($presets)) {
            return ['customizer.presets must be an object of name => settings.'];
        }

        if (count($presets) > 12) {
            return ['customizer.presets may contain at most 12 palettes.'];
        }

        // A palette may set any core design field or any field the theme declares.
        $fields = ThemeSchema::designFields() + $customFields;

        $errors = [];

        foreach ($presets as $name => $settings) {
            if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 60) {
                $errors[] = 'customizer.presets: a palette name must be text of at most 60 characters.';

                continue;
            }

            if (! is_array($settings) || array_is_list($settings)) {
                $errors[] = "customizer.presets.{$name} must be an object of design settings.";

                continue;
            }

            foreach ($settings as $key => $value) {
                if (! array_key_exists((string) $key, $fields)) {
                    $errors[] = "customizer.presets.{$name}.{$key} is not a design setting.";

                    continue;
                }

                [, $error] = ThemeSchema::check($fields[(string) $key], $value, $assetExists, []);

                if ($error !== null) {
                    $errors[] = "customizer.presets.{$name}.{$key} {$error}.";
                }
            }
        }

        return $errors;
    }
}
