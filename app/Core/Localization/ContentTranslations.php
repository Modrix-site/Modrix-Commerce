<?php

namespace App\Core\Localization;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\StoreNavigation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Homepage, footer and theme wording for a language other than the store default.
 *
 * Automatic mode translates when a shopper selects that language and remembers
 * the result. Manual mode uses only the translations the merchant saved.
 * Buttons and labels that already have a storefront translation still use it.
 *
 * Automatic HTTP calls are capped per request so a slow translator cannot
 * take down the storefront.
 */
final class ContentTranslations
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    public const KEY = 'store.translation_mode';

    public const ORIGIN_EDITOR = 'editor';

    public const MAX_TEXT = 10000;

    /** Fresh automatic lookups allowed on one storefront request. */
    private const AUTOMATIC_BUDGET = 8;

    /** Total wall-clock time automatic translation may consume on one page. */
    private const AUTOMATIC_SECONDS = 3;

    private ?bool $tableAvailable = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Locales $locales,
        private readonly InterfaceTranslations $interface,
    ) {}

    public function mode(): string
    {
        return $this->settings->get(self::KEY) === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTOMATIC;
    }

    public function translate(?string $text): string
    {
        $text = trim((string) $text);
        $locale = app()->getLocale();
        if ($text === '' || $locale === $this->locales->default() || ! $this->translatable($text)) {
            return $text;
        }

        $edited = $this->editorValue($locale, $text);
        if ($edited !== null) {
            return $edited;
        }

        $known = $this->interface->messages($locale)[$text] ?? null;
        if (is_string($known) && $known !== '') {
            return $known;
        }

        $saved = $this->stored($locale, $text);
        if ($saved !== null) {
            return $saved;
        }

        return $this->mode() === self::MODE_AUTOMATIC && ! app()->runningUnitTests()
            ? $this->automatic($locale, $text)
            : $text;
    }

    /**
     * Prefetch missing automatic translations for a set of theme phrases in parallel.
     *
     * @param  list<string|null>  $texts
     */
    public function warm(array $texts): void
    {
        if ($this->mode() !== self::MODE_AUTOMATIC || app()->runningUnitTests()) {
            return;
        }

        $locale = app()->getLocale();
        if ($locale === $this->locales->default() || ! $this->tableReady()) {
            return;
        }

        $state = $this->automaticState();
        if ($state['budget'] <= 0 || microtime(true) >= $state['deadline']) {
            return;
        }

        $pending = [];
        foreach ($texts as $text) {
            $text = trim((string) $text);
            if ($text === '' || ! $this->translatable($text) || mb_strlen($text) > 500) {
                continue;
            }
            if ($this->editorValue($locale, $text) !== null) {
                continue;
            }
            $known = $this->interface->messages($locale)[$text] ?? null;
            if (is_string($known) && $known !== '') {
                continue;
            }
            if ($this->stored($locale, $text) !== null) {
                continue;
            }
            if (isset($state['attempted'][$locale.'|'.$this->hash($text)])) {
                continue;
            }
            $pending[$this->hash($text)] = $text;
            if (count($pending) >= $state['budget']) {
                break;
            }
        }

        if ($pending === []) {
            return;
        }

        $state = $this->state();
        $state['budget'] -= count($pending);
        foreach ($pending as $hash => $text) {
            $state['attempted'][$locale.'|'.$hash] = true;
        }
        $this->putState($state);
        $pair = $this->locales->default().'|'.$locale;
        $timeout = max(0.1, min(2, $state['deadline'] - microtime(true)));
        try {
            $responses = Http::pool(fn ($pool) => array_map(
                fn (string $text) => $pool->as($this->hash($text))->connectTimeout(min(1, $timeout))->timeout($timeout)->acceptJson()
                    ->get('https://api.mymemory.translated.net/get', ['q' => $text, 'langpair' => $pair]),
                $pending,
            ));
        } catch (Throwable) {
            $state = $this->state();
            $state['budget'] = 0;
            $this->putState($state);

            return;
        }

        $state = $this->state();
        foreach ($pending as $hash => $text) {
            $response = $responses[$hash] ?? null;
            if (! $response instanceof Response || ! $response->ok()) {
                continue;
            }
            $translated = $this->pickTranslation($text, $response->json());
            if ($translated === null) {
                continue;
            }
            DB::table('content_translations')->updateOrInsert(
                ['locale' => $locale, 'source_hash' => $hash, 'origin' => self::MODE_AUTOMATIC],
                ['source' => $text, 'value' => $translated, 'updated_at' => now(), 'created_at' => now()],
            );
            $state['stored'][$locale.'|'.self::MODE_AUTOMATIC][$hash] = $translated;
        }
        $this->putState($state);
    }

    /**
     * @param  list<array{locale?: string, source?: string, value?: string|null}>  $rows
     */
    public function saveManual(array $rows): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $allowed = array_flip($this->locales->additional());
        foreach ($rows as $row) {
            $locale = (string) ($row['locale'] ?? '');
            $source = trim((string) ($row['source'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if (! isset($allowed[$locale]) || $source === '' || ! $this->translatable($source)) {
                continue;
            }
            $this->forgetRequestCache($locale);
            if ($value === '') {
                DB::table('content_translations')->where('locale', $locale)->where('source_hash', $this->hash($source))->where('origin', self::MODE_MANUAL)->delete();

                continue;
            }
            DB::table('content_translations')->updateOrInsert(
                ['locale' => $locale, 'source_hash' => $this->hash($source), 'origin' => self::MODE_MANUAL],
                ['source' => $source, 'value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /**
     * @return array<string, array<string, string>> locale => source => value
     */
    public function manual(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $grouped = [];
        foreach (DB::table('content_translations')->where('origin', self::MODE_MANUAL)->get(['locale', 'source', 'value']) as $row) {
            $grouped[$row->locale][$row->source] = $row->value;
        }

        return $grouped;
    }

    /**
     * Texts a merchant can translate for the active theme: homepage, footer, then the rest.
     *
     * @return list<array{group: string, text: string}>
     */
    public function phrases(ResolvedTheme $theme): array
    {
        $found = [];
        $add = function (string $group, mixed $text) use (&$found): void {
            if (! is_string($text) || ! $this->translatable($text)) {
                return;
            }
            $found[trim($text)] ??= $group;
        };

        foreach ($theme->manifest->customFields() as $key => $field) {
            if (! in_array($field['type'] ?? '', ['string', 'text', 'textarea'], true) || $this->skipKey($key)) {
                continue;
            }
            $haystack = strtolower($key.' '.($field['group'] ?? ''));
            $group = str_contains($haystack, 'footer') ? 'Footer' : (str_contains($haystack, 'home') ? 'Homepage' : 'Theme');
            $add($group, (string) ($theme->design[$key] ?? $field['default'] ?? ''));
        }
        $add('Homepage', (string) ($theme->design['announcement'] ?? ''));
        $add('Footer', (string) ($theme->design['footer_text'] ?? ''));
        foreach ($theme->sections('home') as $section) {
            foreach ($section['settings'] as $key => $value) {
                if ($this->skipKey((string) $key)) {
                    continue;
                }
                $add('Homepage', $value);
            }
        }
        foreach ($theme->design as $key => $value) {
            if (! is_string($key) || $this->skipKey($key)) {
                continue;
            }
            if (preg_match('/^menu_\d+_label$/', $key) === 1) {
                $add('Theme', $value);
            }
            if (preg_match('/^footer_\d+_(title|\d+_label)$/', $key) === 1) {
                $add('Footer', $value);
            }
        }
        $saved = $this->settings->get(StoreNavigation::KEY);
        $walk = function (mixed $menu, string $group) use (&$walk, $add): void {
            if (! is_array($menu)) {
                return;
            }
            $add($group, $menu['title'] ?? null);
            foreach ($menu['links'] ?? [] as $link) {
                if (is_array($link)) {
                    $add($group, $link['label'] ?? null);
                    $walk($link, $group);
                }
            }
        };
        if (is_array($saved)) {
            foreach (['header' => 'Theme', 'sidebar' => 'Theme', 'mobile' => 'Theme'] as $place => $group) {
                $walk($saved[$place] ?? [], $group);
            }
            foreach ($saved['footer'] ?? [] as $menu) {
                $walk($menu, 'Footer');
            }
        }

        $order = ['Homepage' => 0, 'Footer' => 1, 'Theme' => 2];
        $phrases = [];
        foreach ($found as $text => $group) {
            $phrases[] = ['group' => $group, 'text' => $text];
        }
        usort($phrases, fn (array $a, array $b) => [$order[$a['group']] ?? 9, $a['text']] <=> [$order[$b['group']] ?? 9, $b['text']]);

        return array_slice($phrases, 0, 80);
    }

    public function translatable(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > self::MAX_TEXT) {
            return false;
        }
        if (preg_match('#^(https?:)?//#i', $text) || preg_match('/^#[0-9a-f]{3,8}$/i', $text) || preg_match('/^[\d\s+().-]+$/', $text)) {
            return false;
        }

        return ! (str_contains($text, '@') && ! str_contains($text, ' '));
    }

    public function skipKey(string $key): bool
    {
        // Frontend copy (including Circuit announcements, delivery lines, payment labels).
        if (preg_match('/(?:^|[._])(labels?|title|heading|text|description|placeholder|marquee|brand|announcement(?:_\d+)?|delivery(?:_\d+)?)$/i', $key)) {
            return false;
        }

        return preg_match('/link|url|email|phone|slug|image|color|icon|asset|href|address/i', $key) === 1;
    }

    public function editorValue(string $locale, string $source): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }
        $state = $this->state();
        if (! isset($state['editor'][$locale])) {
            $state['editor'][$locale] = DB::table('content_translations')->where('locale', $locale)
                ->where('origin', self::ORIGIN_EDITOR)->pluck('value', 'source_hash')->all();
            $this->putState($state);
        }
        $value = $state['editor'][$locale][$this->hash(trim($source))] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function saveEditor(string $locale, string $source, ?string $value): void
    {
        $source = trim($source);
        $value = trim((string) $value);
        $this->forgetRequestCache($locale);
        if ($value === '') {
            DB::table('content_translations')->where('locale', $locale)->where('source_hash', $this->hash($source))->where('origin', self::ORIGIN_EDITOR)->delete();

            return;
        }
        DB::table('content_translations')->updateOrInsert(
            ['locale' => $locale, 'source_hash' => $this->hash($source), 'origin' => self::ORIGIN_EDITOR],
            ['source' => $source, 'value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function stored(string $locale, string $text): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $origin = $this->mode();
        $hash = $this->hash($text);
        $cacheKey = $locale.'|'.$origin;
        $state = $this->state();
        if (! array_key_exists($cacheKey, $state['stored'])) {
            $rows = DB::table('content_translations')
                ->where('locale', $locale)
                ->where('origin', $origin)
                ->get(['source_hash', 'source', 'value']);
            $mapped = [];
            foreach ($rows as $row) {
                if (! is_string($row->value) || $row->value === '') {
                    continue;
                }
                // Drop no-op automatic rows that cached the English source as Arabic.
                if ($origin === self::MODE_AUTOMATIC && trim($row->value) === trim((string) $row->source)) {
                    DB::table('content_translations')->where('locale', $locale)->where('source_hash', $row->source_hash)->where('origin', $origin)->delete();

                    continue;
                }
                $mapped[$row->source_hash] = $row->value;
            }
            $state['stored'][$cacheKey] = $mapped;
            $this->putState($state);
        }

        $value = $state['stored'][$cacheKey][$hash] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function automatic(string $locale, string $text): string
    {
        $state = $this->automaticState();
        $key = $locale.'|'.$this->hash($text);
        if ($state['budget'] <= 0 || mb_strlen($text) > 500 || isset($state['attempted'][$key]) || microtime(true) >= $state['deadline']) {
            return $text;
        }

        $state['budget']--;
        $state['attempted'][$key] = true;
        $this->putState($state);
        $timeout = max(0.1, min(2, $state['deadline'] - microtime(true)));

        try {
            $response = Http::connectTimeout(min(1, $timeout))->timeout($timeout)->acceptJson()->get('https://api.mymemory.translated.net/get', [
                'q' => $text,
                'langpair' => $this->locales->default().'|'.$locale,
            ]);
            if (! $response->ok()) {
                $state['budget'] = 0;
                $this->putState($state);

                return $text;
            }
            $translated = $this->pickTranslation($text, $response->json());
            if ($translated === null || ! $this->tableReady()) {
                return $text;
            }
            DB::table('content_translations')->updateOrInsert(
                ['locale' => $locale, 'source_hash' => $this->hash($text), 'origin' => self::MODE_AUTOMATIC],
                ['source' => $text, 'value' => $translated, 'updated_at' => now(), 'created_at' => now()],
            );
            $state = $this->state();
            $state['stored'][$locale.'|'.self::MODE_AUTOMATIC][$this->hash($text)] = $translated;
            $this->putState($state);

            return $translated;
        } catch (Throwable) {
            $state = $this->state();
            $state['budget'] = 0;
            $this->putState($state);

            return $text;
        }
    }

    private function automaticState(): array
    {
        $state = $this->state();
        if ($state['budget'] === null) {
            $state['budget'] = self::AUTOMATIC_BUDGET;
            $state['deadline'] = microtime(true) + self::AUTOMATIC_SECONDS;
            $this->putState($state);
        }

        return $state;
    }

    /**
     * Prefer a real alternate language match when MyMemory's top hit is still English.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function pickTranslation(string $source, ?array $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $candidates = [];
        $top = $payload['responseData']['translatedText'] ?? null;
        if (is_string($top)) {
            $candidates[] = $top;
        }
        foreach ($payload['matches'] ?? [] as $match) {
            if (is_array($match) && is_string($match['translation'] ?? null)) {
                $candidates[] = $match['translation'];
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || str_starts_with($candidate, 'MYMEMORY WARNING')) {
                continue;
            }
            if (mb_strtolower($candidate) === mb_strtolower($source)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function tableReady(): bool
    {
        return $this->tableAvailable ??= Schema::hasTable('content_translations');
    }

    /**
     * @return array{editor: array<string, array<string, string>>, stored: array<string, array<string, string>>, budget: int|null, deadline: float|null, attempted: array<string, true>}
     */
    private function state(): array
    {
        $bag = request()->attributes->get('content_translations_state');
        if (! is_array($bag)) {
            $bag = ['editor' => [], 'stored' => [], 'budget' => null, 'deadline' => null, 'attempted' => []];
            request()->attributes->set('content_translations_state', $bag);
        }

        return $bag;
    }

    /**
     * @param  array{editor: array<string, array<string, string>>, stored: array<string, array<string, string>>, budget: int|null, deadline: float|null, attempted: array<string, true>}  $state
     */
    private function putState(array $state): void
    {
        request()->attributes->set('content_translations_state', $state);
    }

    private function forgetRequestCache(string $locale): void
    {
        if (! app()->bound('request')) {
            return;
        }
        $state = $this->state();
        unset($state['editor'][$locale], $state['stored'][$locale.'|'.self::MODE_AUTOMATIC], $state['stored'][$locale.'|'.self::MODE_MANUAL]);
        $this->putState($state);
    }

    private function hash(string $text): string
    {
        return hash('sha256', $text);
    }
}
