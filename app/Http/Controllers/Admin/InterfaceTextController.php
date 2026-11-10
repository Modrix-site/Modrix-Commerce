<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Localization\InterfaceTranslations;
use App\Core\Localization\Locales;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Languages → Storefront text: the storefront's own wording per
 * language. Shipped translations can be changed; texts without a shipped
 * translation (any language Modrix Commerce does not ship) can be written here.
 */
class InterfaceTextController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly InterfaceTranslations $translations,
        private readonly Locales $locales,
    ) {}

    public function index(Request $request): Response
    {
        $editable = $this->editableLocales();
        $filters = $request->validate([
            'locale' => ['nullable', Rule::in($editable)],
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', Rule::in(['all', 'missing', 'custom'])],
        ]);
        $locale = $filters['locale'] ?? ($editable[0] ?? null);
        $filter = $filters['filter'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $rows = [];
        $counts = null;
        if ($locale !== null) {
            $shipped = $this->translations->shipped($locale);
            $custom = $this->translations->overrides($locale);

            foreach ($this->translations->keys() as $key) {
                $value = $custom[$key] ?? $shipped[$key] ?? null;
                $row = ['key' => $key, 'shipped' => $shipped[$key] ?? null, 'custom' => $custom[$key] ?? null, 'translated' => $value !== null];

                $matches = $search === '' || mb_stripos($key, $search) !== false || ($value !== null && mb_stripos($value, $search) !== false);
                $kept = match ($filter) {
                    'missing' => ! $row['translated'],
                    'custom' => $row['custom'] !== null,
                    default => true,
                };

                if ($matches && $kept) {
                    $rows[] = $row;
                }
            }

            $total = count($this->translations->keys());
            $translated = count(array_filter($this->translations->keys(), fn ($k) => isset($custom[$k]) || isset($shipped[$k])));
            $counts = ['total' => $total, 'translated' => $translated, 'custom' => count($custom)];
        }

        $page = max(1, (int) $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), count($rows), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => array_filter(['locale' => $locale, 'search' => $search, 'filter' => $filter]),
        ]);

        return Inertia::render('admin/settings/interface-text', [
            'locales' => Locales::options($editable),
            'locale' => $locale,
            'filters' => ['search' => $search, 'filter' => $filter],
            'rows' => $paginator,
            'counts' => $counts,
        ]);
    }

    public function update(Request $request, string $locale, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($locale, $this->editableLocales(), true), 404);

        $data = $request->validate([
            'texts' => ['required', 'array', 'max:'.self::PER_PAGE],
            'texts.*.key' => ['required', 'string', 'max:500'],
            'texts.*.value' => ['nullable', 'string', 'max:2000'],
        ]);

        $values = [];
        foreach ($data['texts'] as $row) {
            $values[(string) $row['key']] = isset($row['value']) ? (string) $row['value'] : null;
        }

        $this->translations->save($locale, $values);
        $audit->record('settings.interface_text_updated', null, ['locale' => $locale, 'count' => count($values)]);
        Inertia::flash('toast', ['type' => 'success', 'message' => Locales::name($locale).' texts saved.']);

        return back();
    }

    /**
     * Every enabled storefront language except English (the source language of the texts).
     *
     * @return list<string>
     */
    private function editableLocales(): array
    {
        return array_values(array_filter($this->locales->enabled(), fn (string $l) => $l !== InterfaceTranslations::SOURCE_LOCALE));
    }
}
