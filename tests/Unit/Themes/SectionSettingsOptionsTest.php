<?php

namespace Tests\Unit\Themes;

use App\Core\Themes\Sections\SectionSettings;
use PHPUnit\Framework\TestCase;

/**
 * A theme that lists select options as plain strings is reported as invalid;
 * it must not crash, or one bad theme takes down Admin → Themes.
 */
class SectionSettingsOptionsTest extends TestCase
{
    private const SETTING = ['type' => 'select', 'id' => 'icon', 'label' => 'Icon', 'default' => 'leaf', 'options' => ['leaf', 'heart']];

    public function test_string_options_are_reported_not_crashed(): void
    {
        $this->assertContains('options must list 1-50 {"value", "label"} pairs', SectionSettings::definitionErrors(self::SETTING));
    }

    public function test_string_options_can_still_be_read(): void
    {
        [$value] = SectionSettings::check(self::SETTING, 'heart', fn () => true);
        $this->assertSame('heart', $value);

        $setting = self::SETTING;
        unset($setting['default']);
        $this->assertSame('', SectionSettings::default($setting));
    }
}
