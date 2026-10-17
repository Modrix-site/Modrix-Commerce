<?php

namespace Tests\Unit;

use App\Core\Themes\PageSettingFields;
use PHPUnit\Framework\TestCase;

/**
 * The storefront's fallback defaults (use-store-settings.ts) must match the
 * server's (PageSettingFields::DEFAULTS), or a page rendered without the
 * prop would differ from the real store.
 */
class PageSettingDefaultsSyncTest extends TestCase
{
    public function test_typescript_defaults_match_the_server()
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/use-store-settings.ts');
        preg_match('/DEFAULT_PAGE_SETTINGS: PageSettings = \{(.*?)\n\};/s', $source, $block);
        $this->assertNotEmpty($block, 'DEFAULT_PAGE_SETTINGS not found');

        $ts = [];
        $page = null;

        foreach (explode("\n", $block[1]) as $line) {
            if (preg_match('/^    (\w+): \{$/', $line, $m)) {
                $page = $m[1];
            } elseif ($page !== null && preg_match('/^        (\\w+): (.+),$/', $line, $m)) {
                $ts["{$page}.{$m[1]}"] = json_decode(str_replace("'", '"', $m[2]), true, flags: JSON_THROW_ON_ERROR);
            }
        }

        $this->assertSame(PageSettingFields::DEFAULTS, array_replace(PageSettingFields::DEFAULTS, $ts));
        $this->assertEqualsCanonicalizing(array_keys(PageSettingFields::DEFAULTS), array_keys($ts));
    }
}
