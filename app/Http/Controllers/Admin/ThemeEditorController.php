<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\Sections\SectionPackage;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeResolver;
use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Content\Models\Page;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The theme editor for themes built from sections (capability "sections").
 *
 * Edits stay in the staff member's draft (live preview only) until Save.
 * Save stores them on the theme: on the active theme they are live at once,
 * on another theme Publish then activates it (as on Shopify).
 */
class ThemeEditorController extends Controller
{
    public function __construct(
        private readonly ThemeManager $manager,
        private readonly ThemeTemplates $templates,
        private readonly ThemeCustomizations $customizations,
    ) {}

    public function show(Request $request, Extension $theme, ThemeController $themes): Response|RedirectResponse
    {
        $manifest = $this->manifest($theme);

        if (! ThemeTemplates::supports($manifest)) {
            return redirect()->route('admin.themes.customize', $theme);
        }

        $user = $this->actor($request);
        // A fresh session starts from what is saved.
        $this->templates->forgetDraft($manifest, $user->id);

        // The preview frame renders this theme for this staff member only.
        if ($theme->state !== ModuleState::Enabled) {
            $request->session()->put(ThemeResolver::PREVIEW_SESSION_KEY, $theme->name);
        } else {
            $request->session()->forget(ThemeResolver::PREVIEW_SESSION_KEY);
        }

        [$design] = $this->customizations->effectiveDesign($manifest);
        $fields = [];
        foreach ($manifest->customizerFields() as $key => $field) {
            if (str_starts_with($key, 'menu_') || preg_match('/^footer_[1-3]_/', $key) || PageSettingFields::isPageKey($key)) {
                continue;
            }
            if ($field['type'] === 'font') {
                $field['options'] = [...array_keys(ThemeSchema::FONT_STACKS), ...array_map(fn ($f) => "custom:{$f}", $manifest->customFontFamilies())];
            }
            $fields[] = ['key' => $key, ...$field];
        }

        return Inertia::render('admin/themes/editor', [
            'theme' => $themes->summary($theme),
            'schemas' => $this->templates->schemas($manifest),
            'templates' => collect(ThemeTemplates::keys())->mapWithKeys(fn (string $key) => [$key => $this->templates->saved($manifest, $key)])->all(),
            'defaults' => collect(ThemeTemplates::keys())->mapWithKeys(fn (string $key) => [$key => $this->templates->default($manifest, $key)])->all(),
            'settings' => ['fields' => $fields, 'values' => $design],
            'images' => $themes->imageOptions($manifest),
            'previews' => $this->previewUrls(),
            'urls' => [
                'draft' => route('admin.themes.editor.draft', $theme),
                'save' => route('admin.themes.editor.save', $theme),
                'publish' => route('admin.themes.activate', $theme),
                'upload' => route('admin.themes.uploads.store'),
                'exit' => route('admin.themes.index'),
                'customize' => route('admin.themes.customize', $theme),
            ],
            'limits' => ['sections' => SectionPackage::MAX_SECTIONS, 'blocks' => SectionPackage::MAX_BLOCKS],
        ]);
    }

    /**
     * Live preview: validates and keeps unsaved changes for this staff member.
     */
    public function draft(Request $request, Extension $theme): JsonResponse
    {
        $manifest = $this->manifest($theme);
        abort_unless(ThemeTemplates::supports($manifest), 404);
        $data = $request->validate([
            'templates' => ['sometimes', 'array', 'max:'.count(ThemeTemplates::keys())],
            'design' => ['sometimes', 'array', 'max:500'],
        ]);

        $errors = $this->templates->putDraft($manifest, $this->actor($request)->id, (array) ($data['templates'] ?? []), $data['design'] ?? null);

        return response()->json(['errors' => (object) $errors]);
    }

    /**
     * Save: stores templates, section groups and theme settings on the theme.
     */
    public function save(Request $request, Extension $theme, AuditLogger $audit): JsonResponse
    {
        $manifest = $this->manifest($theme);
        abort_unless(ThemeTemplates::supports($manifest), 404);
        $data = $request->validate([
            'templates' => ['sometimes', 'array', 'max:'.count(ThemeTemplates::keys())],
            'design' => ['sometimes', 'array', 'max:500'],
        ]);
        $user = $this->actor($request);

        $templates = array_filter((array) ($data['templates'] ?? []), fn ($key) => ThemeTemplates::parse((string) $key) !== null, ARRAY_FILTER_USE_KEY);
        $errors = $this->templates->save($manifest, $templates);

        if ($errors !== []) {
            return response()->json(['message' => 'Some sections have invalid settings.', 'errors' => $errors], 422);
        }

        if (isset($data['design'])) {
            try {
                $this->manager->customizeDesign($theme->name, $data['design'], $user);
            } catch (ValidationException $e) {
                return response()->json(['message' => 'Some theme settings are invalid.', 'errors' => ['design' => $e->errors()]], 422);
            }
        }

        $this->templates->forgetDraft($manifest, $user->id);
        $audit->record('theme.templates_saved', null, ['name' => $theme->name, 'templates' => array_keys($templates)], $user);

        return response()->json(['saved' => true, 'templates' => collect(ThemeTemplates::keys())->mapWithKeys(fn (string $key) => [$key => $this->templates->saved($manifest, $key)])->all()]);
    }

    /**
     * Pages the preview can show for each template, with real store content.
     *
     * @return array<string, string|null>
     */
    private function previewUrls(): array
    {
        $product = Product::query()->visible()->latest('id')->value('slug');
        $category = Category::query()->active()->orderBy('position')->value('slug');
        $page = Page::query()->where('status', 'published')->orderBy('id')->value('slug');
        $editor = fn (?string $path) => $path === null ? null : $path.(str_contains($path, '?') ? '&' : '?').'_editor=1';

        return [
            'index' => $editor('/'),
            'product' => $editor($product ? "/p/{$product}" : null),
            'collection' => $editor($category ? "/c/{$category}" : null),
            'search' => $editor('/search?q='),
            'cart' => $editor('/cart'),
            'page' => $editor($page ? "/pages/{$page}" : null),
        ];
    }

    private function manifest(Extension $theme): ThemeManifest
    {
        abort_unless($theme->type === 'theme', 404);
        abort_if(in_array($theme->state, [ModuleState::Invalid, ModuleState::Disabled], true) || $theme->manifest === null, 422, 'Enable this theme before editing it.');

        return $this->manager->renderedManifest($theme);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
