<?php

namespace App\Domains\Catalog\Concerns;

use App\Core\Localization\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Storefront translations for a catalog model. Load the current language
 * with `withCurrentTranslation()`, then read fields with `localized()`:
 * an empty or missing translation falls back to the default-language value.
 *
 * @template TTranslation of Model
 */
trait HasTranslations
{
    /**
     * @return class-string<TTranslation>
     */
    abstract protected static function translationModel(): string;

    /**
     * @return list<string>
     */
    abstract public static function translatableFields(): array;

    /**
     * @return HasMany<TTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(static::translationModel());
    }

    /**
     * Eager-loads only the translation for the request's language.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithCurrentTranslation(Builder $query): Builder
    {
        return $query->with(['translations' => fn ($q) => $q->where('locale', app()->getLocale())]);
    }

    public function localized(string $field): mixed
    {
        $base = $this->getAttribute($field);

        if (! in_array($field, static::translatableFields(), true) || ! app(Locales::class)->translating()) {
            return $base;
        }

        $locale = app()->getLocale();
        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->where('locale', $locale)->get();

        $translation = $translations->firstWhere('locale', $locale);
        $value = $translation?->getAttribute($field);

        return is_string($value) && trim($value) !== '' ? $value : $base;
    }
}
