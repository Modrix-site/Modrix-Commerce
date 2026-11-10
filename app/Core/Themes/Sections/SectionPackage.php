<?php

namespace App\Core\Themes\Sections;

use App\Core\Extensions\ManifestValidator;

/**
 * The sections v2 files of a theme package (capability "sections"):
 *
 *   sections/<type>.json       section schemas, component in src/sections/<type>.tsx
 *   templates/<name>.json      default page templates
 *   groups/header.json, groups/footer.json
 *
 * Reading never executes theme code. The same template check validates the
 * package defaults and every template a merchant saves.
 */
final class SectionPackage
{
    public const TEMPLATES = ['index', 'product', 'collection', 'search', 'cart', 'page'];

    public const GROUPS = ['header', 'footer'];

    public const MAX_SECTIONS = 40;

    public const MAX_BLOCKS = 50;

    public const MAX_SCHEMAS = 100;

    private const ID = '/^[a-z0-9][a-z0-9_-]{0,39}$/';

    private const TYPE = '/^[a-z][a-z0-9_-]{0,59}$/';

    private const MAX_FILE_BYTES = 131072;

    /**
     * @return array{schemas: array<string, array<string, mixed>>, templates: array<string, mixed>, groups: array<string, mixed>, errors: list<string>}
     */
    public static function read(string $packageDir): array
    {
        $errors = [];
        $schemas = [];

        foreach (glob($packageDir.'/sections/*.json') ?: [] as $file) {
            $type = basename($file, '.json');
            $data = self::json($file, "sections/{$type}.json", $errors);

            if (! preg_match(self::TYPE, $type)) {
                $errors[] = "sections/{$type}.json: the file name must be a lowercase section type such as image-banner.";
            } elseif (is_array($data)) {
                $schemas[$type] = $data;
            }
        }

        if (count($schemas) > self::MAX_SCHEMAS) {
            $errors[] = 'A theme may declare at most '.self::MAX_SCHEMAS.' section types.';
        }

        $templates = [];
        foreach (self::TEMPLATES as $name) {
            $file = $packageDir."/templates/{$name}.json";
            if (is_file($file)) {
                $templates[$name] = self::json($file, "templates/{$name}.json", $errors);
            }
        }

        $groups = [];
        foreach (self::GROUPS as $name) {
            $file = $packageDir."/groups/{$name}.json";
            if (is_file($file)) {
                $groups[$name] = self::json($file, "groups/{$name}.json", $errors);
            }
        }

        return ['schemas' => $schemas, 'templates' => $templates, 'groups' => $groups, 'errors' => $errors];
    }

    /**
     * Every problem with the package's sections, templates and groups.
     *
     * @param  callable(string): bool  $assetExists
     * @return list<string>
     */
    public static function errors(string $packageDir, callable $assetExists): array
    {
        $package = self::read($packageDir);
        $errors = $package['errors'];

        if ($package['schemas'] === []) {
            $errors[] = 'The "sections" capability needs at least one section schema in sections/.';
        }

        foreach ($package['schemas'] as $type => $schema) {
            foreach (self::schemaErrors($schema) as $error) {
                $errors[] = "sections/{$type}.json: {$error}";
            }

            if (ManifestValidator::resolvePath($packageDir, "src/sections/{$type}.tsx", directory: false) === null) {
                $errors[] = "sections/{$type}.json has no component src/sections/{$type}.tsx.";
            }
        }

        foreach (self::TEMPLATES as $name) {
            if (! array_key_exists($name, $package['templates'])) {
                $errors[] = "templates/{$name}.json is missing: every page needs a default template.";
            }
        }

        foreach (['template' => $package['templates'], 'group' => $package['groups']] as $kind => $files) {
            foreach ($files as $name => $data) {
                [, $problems] = self::check($package['schemas'], $kind, $name, $data, $assetExists);

                foreach ($problems as $path => $problem) {
                    $errors[] = ($kind === 'template' ? 'templates' : 'groups')."/{$name}.json {$path}: {$problem}";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public static function schemaErrors(mixed $schema): array
    {
        if (! is_array($schema) || array_is_list($schema)) {
            return ['must be a JSON object'];
        }

        $errors = [];

        foreach (array_diff(array_keys($schema), ['name', 'settings', 'blocks', 'max_blocks', 'presets', 'enabled_on', 'limit', 'tag', 'class']) as $unknown) {
            $errors[] = "unknown key \"{$unknown}\"";
        }

        if (! is_string($schema['name'] ?? null) || $schema['name'] === '' || mb_strlen($schema['name']) > 60) {
            $errors[] = 'name is required (max 60)';
        }

        $errors = [...$errors, ...self::settingsErrors($schema['settings'] ?? [], 'settings')];
        $blockTypes = [];

        foreach (self::listOf($schema['blocks'] ?? [], 'blocks', $errors) as $i => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! preg_match(self::TYPE, $block['type'])) {
                $errors[] = "blocks[{$i}].type must be a lowercase type";

                continue;
            }

            if (in_array($block['type'], $blockTypes, true)) {
                $errors[] = "block type {$block['type']} is declared twice";
            }
            $blockTypes[] = $block['type'];

            if (! is_string($block['name'] ?? null) || $block['name'] === '') {
                $errors[] = "blocks[{$i}].name is required";
            }

            if (isset($block['limit']) && (! is_int($block['limit']) || $block['limit'] < 1)) {
                $errors[] = "blocks[{$i}].limit must be a positive integer";
            }

            $errors = [...$errors, ...self::settingsErrors($block['settings'] ?? [], "blocks[{$i}].settings")];
        }

        if (isset($schema['max_blocks']) && (! is_int($schema['max_blocks']) || $schema['max_blocks'] < 1 || $schema['max_blocks'] > self::MAX_BLOCKS)) {
            $errors[] = 'max_blocks must be 1-'.self::MAX_BLOCKS;
        }

        if (isset($schema['limit']) && (! is_int($schema['limit']) || $schema['limit'] < 1)) {
            $errors[] = 'limit must be a positive integer';
        }

        $enabled = $schema['enabled_on'] ?? null;
        if ($enabled !== null) {
            if (! is_array($enabled) || array_diff(array_keys($enabled), ['templates', 'groups']) !== []) {
                $errors[] = 'enabled_on may only list "templates" and "groups"';
            } else {
                foreach ((array) ($enabled['templates'] ?? []) as $t) {
                    if ($t !== '*' && ! in_array($t, self::TEMPLATES, true)) {
                        $errors[] = 'enabled_on.templates lists unknown template "'.(is_string($t) ? $t : '?').'"';
                    }
                }
                foreach ((array) ($enabled['groups'] ?? []) as $g) {
                    if ($g !== '*' && ! in_array($g, self::GROUPS, true)) {
                        $errors[] = 'enabled_on.groups lists unknown group "'.(is_string($g) ? $g : '?').'"';
                    }
                }
            }
        }

        foreach (self::listOf($schema['presets'] ?? [], 'presets', $errors) as $i => $preset) {
            if (! is_array($preset) || ! is_string($preset['name'] ?? null) || $preset['name'] === '') {
                $errors[] = "presets[{$i}].name is required";

                continue;
            }

            foreach ((array) ($preset['blocks'] ?? []) as $j => $block) {
                if (! is_array($block) || ! in_array($block['type'] ?? null, $blockTypes, true)) {
                    $errors[] = "presets[{$i}].blocks[{$j}] uses an undeclared block type";
                }
            }
        }

        return $errors;
    }

    /**
     * Validates and normalises a template or group: unknown section and block
     * types are refused, settings are checked by type and missing settings
     * get their defaults.
     *
     * @param  array<string, array<string, mixed>>  $schemas
     * @param  callable(string): bool  $assetExists
     * @return array{0: array{sections: array<string, mixed>, order: list<string>}, 1: array<string, string>}
     */
    public static function check(array $schemas, string $kind, string $name, mixed $data, callable $assetExists): array
    {
        $errors = [];
        $clean = ['sections' => [], 'order' => []];

        if (! is_array($data) || ! is_array($data['sections'] ?? null) || ! is_array($data['order'] ?? null)) {
            return [$clean, ['' => 'must be an object with "sections" and "order"']];
        }

        $sections = $data['sections'];
        $order = array_values(array_filter($data['order'], 'is_string'));

        if (count($sections) > self::MAX_SECTIONS) {
            $errors[''] = 'has more than '.self::MAX_SECTIONS.' sections';
        }

        if (count($order) !== count(array_unique($order)) || array_diff($order, array_keys($sections)) !== [] || array_diff(array_keys($sections), $order) !== []) {
            $errors['order'] = 'must list every section exactly once';
        }

        $used = [];

        foreach ($order as $id) {
            $section = $sections[$id] ?? null;
            $path = "sections.{$id}";

            if (! preg_match(self::ID, $id) || ! is_array($section)) {
                $errors[$path] = 'is not a valid section';

                continue;
            }

            $type = $section['type'] ?? null;
            $schema = is_string($type) ? ($schemas[$type] ?? null) : null;

            if ($schema === null) {
                $errors[$path] = 'uses a section type the theme does not have';

                continue;
            }

            if (! self::allowedIn($schema, $kind, $name)) {
                $errors[$path] = "\"{$schema['name']}\" cannot be added to this ".($kind === 'group' ? 'area' : 'page');

                continue;
            }

            $used[$type] = ($used[$type] ?? 0) + 1;
            if (isset($schema['limit']) && $used[$type] > $schema['limit']) {
                $errors[$path] = "\"{$schema['name']}\" can be added only {$schema['limit']} time(s)";
            }

            [$settings, $settingErrors] = self::checkSettings((array) ($schema['settings'] ?? []), (array) ($section['settings'] ?? []), $assetExists);
            foreach ($settingErrors as $key => $error) {
                $errors["{$path}.settings.{$key}"] = $error;
            }

            [$blocks, $blockOrder] = [[], []];
            $blockSchemas = collect((array) ($schema['blocks'] ?? []))->keyBy('type')->all();
            $rawBlocks = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];
            $rawOrder = array_values(array_filter((array) ($section['block_order'] ?? array_keys($rawBlocks)), 'is_string'));
            $max = min((int) ($schema['max_blocks'] ?? self::MAX_BLOCKS), self::MAX_BLOCKS);
            $blockCounts = [];

            if (count($rawOrder) > $max) {
                $errors["{$path}.blocks"] = "can have at most {$max} blocks";
            }

            foreach ($rawOrder as $blockId) {
                $block = $rawBlocks[$blockId] ?? null;
                $blockPath = "{$path}.blocks.{$blockId}";
                $blockSchema = is_array($block) && is_string($block['type'] ?? null) ? ($blockSchemas[$block['type']] ?? null) : null;

                if (! preg_match(self::ID, $blockId) || $blockSchema === null || isset($blocks[$blockId])) {
                    $errors[$blockPath] = 'is not a block this section accepts';

                    continue;
                }

                $blockCounts[$block['type']] = ($blockCounts[$block['type']] ?? 0) + 1;
                if (isset($blockSchema['limit']) && $blockCounts[$block['type']] > $blockSchema['limit']) {
                    $errors[$blockPath] = "\"{$blockSchema['name']}\" can be added only {$blockSchema['limit']} time(s)";
                }

                [$blockSettings, $blockErrors] = self::checkSettings((array) ($blockSchema['settings'] ?? []), (array) ($block['settings'] ?? []), $assetExists);
                foreach ($blockErrors as $key => $error) {
                    $errors["{$blockPath}.settings.{$key}"] = $error;
                }

                $blocks[$blockId] = ['type' => $block['type'], 'settings' => $blockSettings] + (($block['disabled'] ?? false) === true ? ['disabled' => true] : []);
                $blockOrder[] = $blockId;
            }

            $clean['sections'][$id] = ['type' => $type, 'settings' => $settings, 'blocks' => $blocks, 'block_order' => $blockOrder]
                + (($section['disabled'] ?? false) === true ? ['disabled' => true] : []);
            $clean['order'][] = $id;
        }

        return [$clean, $errors];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function allowedIn(array $schema, string $kind, string $name): bool
    {
        $enabled = $schema['enabled_on'] ?? null;

        // Without enabled_on a section fits every template but no group (Shopify's default).
        if ($enabled === null) {
            return $kind === 'template';
        }

        $list = (array) ($enabled[$kind === 'group' ? 'groups' : 'templates'] ?? []);

        return in_array('*', $list, true) || in_array($name, $list, true);
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $values
     * @param  callable(string): bool  $assetExists
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function checkSettings(array $definitions, array $values, callable $assetExists): array
    {
        $clean = [];
        $errors = [];

        foreach (SectionSettings::byId($definitions) as $id => $definition) {
            if (! array_key_exists($id, $values)) {
                $clean[$id] = SectionSettings::default($definition);

                continue;
            }

            [$value, $error] = SectionSettings::check($definition, $values[$id], $assetExists);

            if ($error !== null) {
                $errors[$id] = "\"{$definition['label']}\" {$error}";
                $clean[$id] = SectionSettings::default($definition);
            } else {
                $clean[$id] = $value;
            }
        }

        return [$clean, $errors];
    }

    /**
     * @return list<string>
     */
    private static function settingsErrors(mixed $settings, string $where): array
    {
        $errors = [];

        foreach (self::listOf($settings, $where, $errors) as $i => $setting) {
            foreach (is_array($setting) ? SectionSettings::definitionErrors($setting) : ['must be an object'] as $error) {
                $errors[] = "{$where}[{$i}] {$error}";
            }
        }

        $ids = array_keys(SectionSettings::byId(array_filter((array) $settings, 'is_array')));
        $all = array_filter(array_map(fn ($s) => is_array($s) ? ($s['id'] ?? null) : null, (array) $settings));
        if (count($all) !== count($ids)) {
            $errors[] = "{$where} repeats a setting id";
        }

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     * @return list<mixed>
     */
    private static function listOf(mixed $value, string $where, array &$errors): array
    {
        if (! is_array($value) || ($value !== [] && ! array_is_list($value))) {
            $errors[] = "{$where} must be a list";

            return [];
        }

        return $value;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function json(string $file, string $label, array &$errors): mixed
    {
        if (filesize($file) > self::MAX_FILE_BYTES) {
            $errors[] = "{$label} is larger than 128 KB.";

            return null;
        }

        $data = json_decode((string) file_get_contents($file), true, 64);

        if (! is_array($data)) {
            $errors[] = "{$label} is not valid JSON.";

            return null;
        }

        return $data;
    }
}
