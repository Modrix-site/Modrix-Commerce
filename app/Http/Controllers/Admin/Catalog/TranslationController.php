<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Content\RichText;
use App\Core\Localization\Locales;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\CategoryTranslation;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront translations of products and categories: an overview of what
 * is missing per language, and an editor per product and per category that
 * shows the default-language text next to each field.
 */
class TranslationController extends Controller
{
    private const PRODUCT_LIMITS = ['name' => 191, 'short_description' => 500, 'description' => 50000, 'meta_title' => 191, 'meta_description' => 500];

    private const CATEGORY_LIMITS = ['name' => 160, 'description' => 20000, 'meta_title' => 191, 'meta_description' => 500];

    public function __construct(private readonly Locales $locales) {}

    public function index(Request $request): Response
    {
        $additional = $this->locales->additional();
        $data = $request->validate(['locale' => ['nullable', Rule::in($additional)], 'type' => ['nullable', Rule::in(['products', 'categories'])]]);
        $locale = $data['locale'] ?? ($additional[0] ?? null);
        $type = $data['type'] ?? 'products';

        $productTotal = Product::query()->count();
        $categoryTotal = Category::query()->count();

        $stats = array_map(fn (string $code) => [
            'code' => $code,
            'name' => Locales::name($code),
            'products' => ProductTranslation::query()->where('locale', $code)->whereNotNull('name')->where('name', '!=', '')->count(),
            'categories' => CategoryTranslation::query()->where('locale', $code)->whereNotNull('name')->where('name', '!=', '')->count(),
        ], $additional);

        $missing = null;
        if ($locale !== null) {
            $model = $type === 'products' ? Product::query() : Category::query();
            $missing = $model->whereDoesntHave('translations', fn ($q) => $q->where('locale', $locale)->whereNotNull('name')->where('name', '!=', ''))
                ->orderBy('name')->paginate(25)->withQueryString()
                ->through(fn (Model $m) => [
                    'id' => (int) $m->getKey(),
                    'name' => (string) $m->getAttribute('name'),
                    'url' => $m instanceof Product ? route('admin.products.translations.edit', $m) : route('admin.categories.translations.edit', $m),
                ]);
        }

        return Inertia::render('admin/translations/index', [
            'defaultLocale' => ['code' => $this->locales->default(), 'name' => Locales::name($this->locales->default())],
            'stats' => $stats,
            'totals' => ['products' => $productTotal, 'categories' => $categoryTotal],
            'locale' => $locale,
            'type' => $type,
            'missing' => $missing,
        ]);
    }

    public function editProduct(Product $product): Response
    {
        return $this->editor('product', $product, Product::translatableFields(), route('admin.products.edit', $product));
    }

    public function updateProduct(Request $request, Product $product, string $locale, AuditLogger $audit): RedirectResponse
    {
        return $this->save($request, $product, $locale, self::PRODUCT_LIMITS, $audit, 'product.translated');
    }

    public function editCategory(Category $category): Response
    {
        return $this->editor('category', $category, Category::translatableFields(), route('admin.categories.edit', $category));
    }

    public function updateCategory(Request $request, Category $category, string $locale, AuditLogger $audit): RedirectResponse
    {
        return $this->save($request, $category, $locale, self::CATEGORY_LIMITS, $audit, 'category.translated');
    }

    /**
     * @param  list<string>  $fields
     */
    private function editor(string $kind, Product|Category $model, array $fields, string $backUrl): Response
    {
        $additional = $this->locales->additional();
        $translations = $model->translations()->whereIn('locale', $additional)->get()->keyBy('locale');
        $value = fn (?Model $row, string $field) => $kind === 'product' && $field === 'description'
            ? RichText::toHtml($row?->getAttribute($field))
            : $row?->getAttribute($field);

        return Inertia::render('admin/translations/edit', [
            'kind' => $kind,
            'item' => ['id' => $model->id, 'name' => $model->name, 'slug' => $model->slug, 'backUrl' => $backUrl],
            'fields' => $fields,
            'defaultLocale' => ['code' => $this->locales->default(), 'name' => Locales::name($this->locales->default())],
            'source' => collect($fields)->mapWithKeys(fn (string $f) => [$f => $value($model, $f)]),
            'locales' => array_map(fn (string $code) => [
                'code' => $code,
                'name' => Locales::name($code),
                'values' => collect($fields)->mapWithKeys(fn (string $f) => [$f => $value($translations->get($code), $f)]),
                'updateUrl' => $kind === 'product'
                    ? route('admin.products.translations.update', [$model, $code])
                    : route('admin.categories.translations.update', [$model, $code]),
            ], $additional),
            'languagesUrl' => route('admin.settings.languages.edit'),
        ]);
    }

    /**
     * @param  array<string, int>  $limits
     */
    private function save(Request $request, Product|Category $model, string $locale, array $limits, AuditLogger $audit, string $action): RedirectResponse
    {
        abort_unless(in_array($locale, $this->locales->additional(), true), 404);

        $data = $request->validate(collect($limits)->mapWithKeys(fn (int $max, string $field) => [$field => ['nullable', 'string', "max:{$max}"]])->all());
        /** Empty fields are stored as null so the storefront falls back to the default language. */
        $values = [];
        foreach (array_keys($limits) as $field) {
            $value = $data[$field] ?? null;
            $values[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if ($model instanceof Product) {
            $values['description'] = RichText::clean($values['description']);
        }

        if (array_filter($values) === []) {
            $model->translations()->where('locale', $locale)->delete();
        } else {
            $model->translations()->updateOrCreate(['locale' => $locale], $values);
        }

        $audit->record($action, $model, ['locale' => $locale]);
        Inertia::flash('toast', ['type' => 'success', 'message' => Locales::name($locale).' translation saved.']);

        return back();
    }
}
