<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Newsletter sign-ups in the footer place record source "footer".
 * Commit with the storefront footer place (storefront.footer).
 */
class NewsletterFooterPlaceTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    public function test_sign_ups_in_the_footer_record_footer()
    {
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules/ecomodular/newsletter'), $this->modulesPath.'/ecomodular/newsletter');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('ecomodular/newsletter');
        app(ModuleManager::class)->enable('ecomodular/newsletter', true);

        app(SettingsRepository::class)->set(BlockPlacements::KEY, ['ecomodular/newsletter:signup-home' => ['place' => 'storefront.footer', 'order' => 1, 'enabled' => true]]);
        $footer = app(StorefrontSlots::class)->render('storefront.footer', []);

        $this->assertNotEmpty($footer);
        $this->assertSame(['footer'], array_values(array_unique(array_column(array_column($footer, 'props'), 'source'))));
    }
}
