<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifestValidator;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeOwnedCustomizerTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
    }

    private function package(string $name = 'acme/sunny', array $schema = []): Extension
    {
        $directory = $this->makeTheme($name, overrides: [
            'customizer_file' => 'settings.json',
            'frontend' => ['customizer' => 'src/customizer.tsx'],
            'design' => ['color.primary' => '#123456'],
        ]);
        File::ensureDirectoryExists($directory.'/src');
        File::put($directory.'/src/customizer.tsx', 'export default function Customizer() { return null; }');
        File::put($directory.'/settings.json', json_encode($schema + [
            'title' => 'Package design',
            'fields' => ['custom.headline' => ['type' => 'string', 'label' => 'Headline', 'default' => 'Package default', 'max' => 100]],
        ], JSON_THROW_ON_ERROR));
        app(ThemeRepository::class)->sync();

        return Extension::query()->where('name', $name)->firstOrFail();
    }

    public function test_an_inactive_theme_supplies_its_own_editor_schema_and_defaults(): void
    {
        $theme = $this->package(schema: ['mode' => 'replace', 'core_fields' => ['color.primary'], 'sections' => false]);
        $response = $this->actingAs($this->userWithRole('administrator'))->get(route('admin.themes.customize', $theme));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('theme.name', 'acme/sunny')
            ->where('customizer.component', 'src/customizer.tsx')
            ->where('customizer.mode', 'replace')
            ->where('customizer.sections', false)
            ->where('design.title', 'Package design')
            ->where('design.defaults', fn ($defaults) => $defaults['color.primary'] === '#123456' && $defaults['custom.headline'] === 'Package default')
            ->where('design.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['color.primary', 'custom.headline']));
    }

    public function test_replace_mode_can_omit_all_core_fields_and_extend_mode_is_backwards_compatible(): void
    {
        $theme = $this->package(schema: ['mode' => 'replace']);
        $this->assertSame(['custom.headline'], array_keys(app(ThemeManager::class)->renderedManifest($theme)->customizerFields()));
        $other = $this->package('acme/other');
        $this->assertArrayHasKey('color.primary', app(ThemeManager::class)->renderedManifest($other)->customizerFields());
    }

    public function test_saves_are_validated_isolated_and_preserve_hidden_overrides(): void
    {
        $theme = $this->package(schema: ['mode' => 'replace']);
        $other = $this->package('acme/other');
        $manager = app(ThemeManager::class);
        $manager->customizeDesign($theme->name, ['color.primary' => '#654321']);
        $this->actingAs($this->userWithRole('administrator'))->put(route('admin.themes.design', $theme), ['design' => ['custom.headline' => 'My campaign']])->assertRedirect()->assertSessionHasNoErrors();
        $values = app(ThemeCustomizations::class)->designOverrides($manager->renderedManifest($theme->fresh()));
        $this->assertSame('My campaign', $values['custom.headline']);
        $this->assertSame('#654321', $values['color.primary']);
        $this->assertArrayNotHasKey('custom.headline', app(ThemeCustomizations::class)->designOverrides($manager->renderedManifest($other)));
        $this->put(route('admin.themes.design', $theme), ['design' => ['custom.headline' => str_repeat('x', 101)]])->assertSessionHasErrors();
    }

    #[DataProvider('invalidSchemas')]
    public function test_invalid_theme_editor_configuration_is_rejected(array $schema): void
    {
        $directory = $this->makeTheme(overrides: ['customizer' => $schema]);
        [$manifest, $errors] = app(ThemeManifestValidator::class)->validate($directory, 'acme/sunny', json_decode(File::get($directory.'/theme.json'), true));
        $this->assertNull($manifest);
        $this->assertNotEmpty($errors);
    }

    public static function invalidSchemas(): array
    {
        return [
            'unknown mode' => [['mode' => 'unknown']],
            'invalid core list' => [['core_fields' => 'all']],
            'unknown core field' => [['core_fields' => ['not.a.field']]],
            'wrong section toggle' => [['sections' => 'false']],
        ];
    }
}
