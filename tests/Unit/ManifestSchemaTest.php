<?php

namespace Tests\Unit;

use App\Core\Extensions\Manifest;
use PHPUnit\Framework\TestCase;

/**
 * The published JSON Schema must describe what the validator accepts.
 */
class ManifestSchemaTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/docs/extensions/module.schema.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    public function test_capabilities_match_the_validator()
    {
        $this->assertEqualsCanonicalizing(array_keys(Manifest::CAPABILITIES), $this->schema()['properties']['capabilities']['items']['enum']);
    }

    public function test_setting_types_match_the_validator()
    {
        $this->assertEqualsCanonicalizing(Manifest::SETTING_TYPES, $this->schema()['properties']['settings']['items']['properties']['type']['enum']);
    }

    public function test_bundled_manifests_reference_the_schema_and_use_only_known_keys()
    {
        $keys = array_keys($this->schema()['properties']);

        foreach (glob(dirname(__DIR__, 2).'/modules/*/*/module.json') ?: [] as $file) {
            $manifest = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);

            $this->assertSame('../../../docs/extensions/module.schema.json', $manifest['$schema'], $file);
            $this->assertSame([], array_diff(array_keys($manifest), $keys), $file);
        }
    }
}
