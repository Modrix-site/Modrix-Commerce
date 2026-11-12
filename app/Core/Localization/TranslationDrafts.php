<?php

namespace App\Core\Localization;

use Closure;
use Illuminate\Validation\ValidationException;

/** Optional translation provider registered by an enabled extension. */
final class TranslationDrafts
{
    /** @var (Closure(string, string, string): string)|null */
    private ?Closure $translator = null;

    /** @var (Closure(): bool)|null */
    private ?Closure $enabled = null;

    /**
     * @param  Closure(string, string, string): string  $translator
     * @param  Closure(): bool  $enabled
     */
    public function register(Closure $translator, Closure $enabled): void
    {
        $this->translator = $translator;
        $this->enabled = $enabled;
    }

    public function available(): bool
    {
        return $this->translator !== null && $this->enabled !== null && ($this->enabled)();
    }

    public function draft(string $source, string $sourceLocale, string $targetLocale): string
    {
        $translator = $this->translator;
        if ($translator === null || ! $this->available()) {
            throw ValidationException::withMessages(['ai' => 'Enable and configure an AI translation provider in AI Assistant settings.']);
        }

        return $translator($source, $sourceLocale, $targetLocale);
    }
}
