<?php

namespace Tests\Unit;

use App\Core\Themes\ThemeSchema;
use PHPUnit\Framework\TestCase;

/**
 * The published JSON Schema must match the vocabulary the validator enforces,
 * and links must stay safe.
 */
class ThemeSchemaTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/docs/extensions/theme.schema.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    public function test_design_keys_and_options_match_the_validator()
    {
        $properties = $this->schema()['properties']['design']['properties'];

        $this->assertEqualsCanonicalizing(array_keys(ThemeSchema::designFields()), array_keys($properties));

        foreach (ThemeSchema::designFields() as $key => $field) {
            if ($field['type'] === 'select') {
                $this->assertSame($field['options'], $properties[$key]['enum'], $key);
            }
        }
    }

    public function test_capabilities_section_types_and_fonts_match_the_validator()
    {
        $schema = $this->schema();

        $this->assertEqualsCanonicalizing(array_keys(ThemeSchema::CAPABILITIES), $schema['properties']['capabilities']['items']['enum']);
        $this->assertEqualsCanonicalizing(array_keys(ThemeSchema::sectionTypes()), $schema['properties']['sections']['properties']['home']['items']['properties']['type']['enum']);
        $this->assertEqualsCanonicalizing(array_keys(ThemeSchema::FONT_STACKS), $schema['$defs']['font']['anyOf'][0]['enum']);
    }

    public function test_only_internal_paths_and_https_links_are_safe()
    {
        foreach (['/search', '/c/mugs?sort=name', '/p/tea#reviews', 'https://example.com/about'] as $link) {
            $this->assertTrue(ThemeSchema::isSafeLink($link), $link);
        }

        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.test', '/\\evil.test', 'http://example.com', 'https://exa mple.com', '/a"onmouseover="x', 'vbscript:x'] as $link) {
            $this->assertFalse(ThemeSchema::isSafeLink($link), $link);
        }
    }
}
