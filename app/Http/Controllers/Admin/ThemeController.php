<?php

namespace App\Http\Controllers\Admin;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\Models\ExtensionEvent;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Extensions\TabSettings;
use App\Core\Themes\CustomizerTabs;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeException;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeResolver;
use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\ThemeDemoImporter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Composer\Semver\Comparator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Admin → Appearance: theme list, preview, activation, customization and
 * updates. Every change goes through ThemeManager. Requires `themes.manage`.
 */
class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeRepository $repository,
        private readonly ThemeManager $manager,
        private readonly ThemeCustomizations $customizations,
    ) {}

    public function index(Request $request, ThemeResolver $resolver): Response
    {
        $this->repository->sync($this->actor($request));

        return Inertia::render('admin/themes/index', [
            'themes' => $this->repository->all()->map(fn (Extension $t) => $this->summary($t))->values(),
            'previewing' => $resolver->previewing($request),
            'defaultTheme' => config('ecomodular.themes.default'),
        ]);
    }

    /**
     * Every extension block with its places and the merchant's placement.
     *
     * @return list<array<string, mixed>>
     */
    private function extensionBlocks(): array
    {
        $placements = app(BlockPlacements::class);

        return array_map(function (array $block) use ($placements) {
            $saved = $placements->for($block['id']);
            $place = $saved !== null && in_array($saved['place'], $block['places'], true) ? $saved['place'] : $block['default'];

            return [
                'id' => $block['id'],
                'module' => $block['module'],
                'label' => $block['label'],
                'places' => array_map(fn (string $key) => ['key' => $key, 'label' => StorefrontSlots::PLACES[$key]['label'], 'tab' => StorefrontSlots::PLACES[$key]['tab']], $block['places']),
                'default' => $block['default'],
                'place' => $place,
                'order' => $saved['order'] ?? $block['order'],
                'enabled' => $saved['enabled'] ?? true,
            ];
        }, app(StorefrontSlots::class)->blocks());
    }

    public function customize(Extension $theme): Response
    {
        $this->assertTheme($theme);
        $manifest = $this->manager->renderedManifest($theme);
        [$design, $ignored] = $this->customizations->effectiveDesign($manifest);

        $fields = [];
        foreach (array_diff_key($manifest->designFields(), array_flip($manifest->customizer['hide_core_fields'] ?? [])) as $key => $field) {
            if (str_starts_with($key, 'menu_') || preg_match('/^footer_[1-3]_/', $key)) {
                continue; // Links are managed globally in Admin → Navigation.
            }
            if ($field['type'] === 'font') {
                $field['options'] = [...array_keys(ThemeSchema::FONT_STACKS), ...array_map(fn ($f) => "custom:{$f}", $manifest->customFontFamilies())];
            }
            $fields[] = ['key' => $key, ...$field, 'tab' => CustomizerTabs::for($key, $field)];
        }

        return Inertia::render('admin/themes/customize', [
            'theme' => $this->summary($theme),
            'design' => [
                'title' => $manifest->customizer['title'] ?? 'Design',
                'description' => $manifest->customizer['description'] ?? null,
                'fields' => $fields,
                'values' => $design,
                // Page settings the theme does not set show the platform's default.
                'defaults' => $manifest->defaults() + PageSettingFields::displayDefaults(),
                'overridden' => array_keys($this->customizations->designOverrides($manifest)),
                'ignored' => $ignored,
                'presets' => $manifest->presets(),
            ],
            'sections' => [
                'types' => collect(ThemeSchema::sectionTypes())->map(fn ($type, $key) => [
                    'key' => $key,
                    'label' => $type['label'],
                    'fields' => collect($type['fields'])->map(fn ($f, $k) => ['key' => $k, ...$f])->values(),
                ])->values(),
                'home' => $this->customizations->sections($manifest, 'home'),
                'customized' => $this->customizations->hasSectionOverrides($manifest, 'home'),
                'max' => ThemeSchema::MAX_SECTIONS,
            ],
            'images' => $this->imageOptions($manifest),
            // Extension blocks (store-wide placement) and extension settings per tab.
            'blocks' => fn () => $this->extensionBlocks(),
            'extensionSettings' => fn () => collect(array_keys(CustomizerTabs::TABS))->mapWithKeys(fn (string $tab) => [$tab => app(TabSettings::class)->forTab($tab)])->all(),
        ]);
    }

    public function activate(Request $request, Extension $theme): RedirectResponse
    {
        return $this->run($theme, fn () => $this->manager->activate($theme->name, $this->actor($request)), "{$theme->display_name} is now your store's theme.");
    }

    public function disable(Request $request, Extension $theme): RedirectResponse
    {
        return $this->run($theme, fn () => $this->manager->disable($theme->name, $this->actor($request)), 'Theme disabled. The storefront uses its fallback design.');
    }

    public function enable(Request $request, Extension $theme): RedirectResponse
    {
        return $this->activate($request, $theme);
    }

    public function destroy(Request $request, Extension $theme): RedirectResponse
    {
        return $this->run($theme, fn () => $this->manager->delete($theme->name, $this->actor($request)), 'Theme deleted. An archive and your customizations were kept.');
    }

    public function update(Request $request, Extension $theme): RedirectResponse
    {
        return $this->run($theme, function () use ($request, $theme) {
            $result = $this->manager->applyUpdate($theme->name, $this->actor($request));

            if ($result['ignored'] !== []) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'Updated. Some of your changes no longer apply to this version: '.implode(', ', $result['ignored']).'.']);
            }
        }, 'Theme updated. Your customizations were kept.');
    }

    public function preview(Request $request, Extension $theme): RedirectResponse
    {
        $this->assertTheme($theme);
        abort_if(in_array($theme->state, [ModuleState::Invalid, ModuleState::Disabled], true) || $theme->manifest === null, 422, 'Enable this theme before previewing it.');

        $request->session()->put(ThemeResolver::PREVIEW_SESSION_KEY, $theme->name);
        ExtensionEvent::query()->forceCreate([
            'extension' => "theme:{$theme->name}",
            'action' => 'previewed',
            'to_state' => $theme->state->value,
            'version' => $theme->available_version,
            'actor_id' => $this->actor($request)->id,
        ]);

        return redirect()->route('home');
    }

    public function exitPreview(Request $request): RedirectResponse
    {
        $request->session()->forget(ThemeResolver::PREVIEW_SESSION_KEY);

        return redirect()->route('admin.themes.index');
    }

    public function design(Request $request, Extension $theme): RedirectResponse
    {
        $input = (array) $request->input('design', []);

        return $this->run($theme, fn () => $this->manager->customizeDesign($theme->name, $input, $this->actor($request)), 'Design saved.', back: true);
    }

    public function sections(Request $request, Extension $theme): RedirectResponse
    {
        $sections = $request->input('sections', []);

        return $this->run($theme, fn () => $this->manager->customizeSections($theme->name, 'home', $sections, $this->actor($request)), 'Home page saved.', back: true);
    }

    public function reset(Request $request, Extension $theme): RedirectResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:100']]);

        return $this->run($theme, fn () => $this->manager->reset($theme->name, $data['confirmation'], $this->actor($request)), 'Theme reset to its defaults.', back: true);
    }

    public function upload(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        abort_unless($file !== null && ! is_array($file), 422);

        $image = $this->customizations->upload($file);
        if ($request->expectsJson()) {
            return response()->json(['image' => ['value' => $image['value'], 'label' => $image['name'], 'url' => $image['url']]]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image uploaded. You can now choose it in any image setting.')]);

        return back();
    }

    public function demo(Request $request, Extension $theme, ThemeDemoImporter $importer): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']], ['confirmed.accepted' => 'Confirm that demo content may be added to your store.']);

        return $this->run($theme, function () use ($request, $theme, $importer) {
            $result = $importer->import($this->manager->renderedManifest($theme), $this->actor($request));
            Inertia::flash('toast', ['type' => 'success', 'message' => "Demo content added: {$result['categories']} categories and {$result['products']} products. Existing content was not changed."]);
        }, '', back: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Extension $theme): array
    {
        $data = $theme->state === ModuleState::Enabled && $theme->applied_manifest !== null ? $theme->applied_manifest : $theme->manifest;
        $manifest = $data === null ? null : ThemeManifest::fromArray($data);
        $design = $manifest === null ? [] : $this->customizations->effectiveDesign($manifest)[0];
        $disk = $theme->manifest === null ? null : ThemeManifest::fromArray($theme->manifest);
        $updateAvailable = $theme->state === ModuleState::Enabled && $theme->version !== null && $disk !== null
            && $theme->errors === null && Comparator::greaterThan($disk->version, $theme->version);

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'display_name' => $theme->display_name,
            'description' => $theme->description,
            'author' => $manifest?->author,
            'state' => match ($theme->state) {
                ModuleState::Enabled => 'active',
                ModuleState::Invalid => 'invalid',
                ModuleState::Failed => 'failed',
                ModuleState::Disabled => 'disabled',
                default => 'available',
            },
            'version' => $theme->state === ModuleState::Enabled ? $theme->version : $theme->available_version,
            'available_version' => $theme->available_version,
            'update_available' => $updateAvailable,
            'changelog' => $updateAvailable
                ? array_values(array_filter($disk->changelog, fn ($e) => Comparator::greaterThan($e['version'], (string) $theme->version)))
                : [],
            'errors' => $theme->errors ?? [],
            'failure' => $theme->failure,
            'swatches' => array_values(array_filter([
                $design['color.background'] ?? null,
                $design['color.primary'] ?? null,
                $design['color.accent'] ?? null,
                $design['color.foreground'] ?? null,
            ])),
            'fonts' => $manifest === null ? null : [$design['font_heading'] ?? 'sans', $design['font_body'] ?? 'sans'],
            'screenshot' => $manifest?->screenshot !== null ? route('theme.asset', ['vendor' => $manifest->vendor(), 'theme' => $manifest->package(), 'path' => $manifest->screenshot, 'v' => $manifest->version]) : null,
            'capabilities' => array_map(fn (string $c) => ThemeSchema::CAPABILITIES[$c] ?? $c, $manifest->capabilities ?? []),
            'has_demo' => $manifest?->demoContent !== null,
            'customized' => $manifest !== null && (array_filter($this->customizations->designOverrides($manifest), fn (string $key) => ! PageSettingFields::isPageKey($key), ARRAY_FILTER_USE_KEY) !== [] || $this->customizations->hasSectionOverrides($manifest, 'home')),
        ];
    }

    /**
     * Images the merchant can pick: the theme's own assets and their uploads.
     *
     * @return list<array{value: string, label: string, url: string}>
     */
    private function imageOptions(ThemeManifest $manifest): array
    {
        $options = [];
        $dir = ThemeRepository::packageDir($manifest->name);
        $assets = $manifest->assets === null ? null : ManifestValidator::resolvePath($dir, $manifest->assets, directory: true);

        if ($assets !== null) {
            $count = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assets, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (++$count > 200 || ! in_array(strtolower($file->getExtension()), ThemeSchema::IMAGE_EXTENSIONS, true)) {
                    continue;
                }

                $relative = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($dir)), '/\\'));
                $options[] = [
                    'value' => "asset:{$relative}",
                    'label' => 'Theme: '.basename($relative),
                    'url' => route('theme.asset', ['vendor' => $manifest->vendor(), 'theme' => $manifest->package(), 'path' => $relative, 'v' => $manifest->version]),
                ];
            }
        }

        foreach ($this->customizations->uploads() as $upload) {
            $options[] = ['value' => $upload['value'], 'label' => 'Uploaded: '.$upload['name'], 'url' => $this->customizations->mediaUrl($upload['value'])];
        }

        return $options;
    }

    /**
     * @param  Closure(): mixed  $operation
     */
    private function run(Extension $theme, Closure $operation, string $success, bool $back = false): RedirectResponse
    {
        $this->assertTheme($theme);

        try {
            $operation();
        } catch (ThemeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        if ($success !== '') {
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        }

        return $back ? back() : redirect()->route('admin.themes.index');
    }

    private function assertTheme(Extension $theme): void
    {
        abort_unless($theme->type === 'theme', 404);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
