<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\Models\Extension;
use App\Core\Localization\ContentTranslations;
use App\Core\Localization\Locales;
use App\Core\Localization\TranslationDrafts;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeSchema;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ThemeTextTranslationController extends Controller
{
    public function handle(Request $request, Extension $theme, Locales $locales, ContentTranslations $content, TranslationDrafts $drafts, ThemeManager $themes, AuditLogger $audit): JsonResponse
    {
        abort_unless($theme->type === 'theme', 404);
        $data = $request->validate([
            'operation' => ['required', Rule::in(['read', 'draft', 'save'])],
            'locale' => ['required', Rule::in($locales->additional())],
            'key' => ['required', 'string', 'max:150'],
            'section_type' => ['nullable', 'string', 'max:64'],
            'source' => ['required', 'string', 'max:'.ContentTranslations::MAX_TEXT],
            'value' => ['nullable', 'string', 'max:'.ContentTranslations::MAX_TEXT],
        ]);
        $manifest = $themes->renderedManifest($theme);
        $field = isset($data['section_type'])
            ? (ThemeSchema::sectionTypes()[$data['section_type']]['fields'][$data['key']] ?? null)
            : ($manifest->designFields()[$data['key']] ?? null);
        if (! is_array($field) || ! in_array($field['type'], ['string', 'text'], true)
            || $content->skipKey($data['key']) || ! $content->translatable($data['source'])) {
            throw ValidationException::withMessages(['source' => 'Choose a frontend text field. Links, contact details and design values are not translated.']);
        }
        $source = trim($data['source']);
        if ($data['operation'] === 'draft') {
            $rateKey = 'theme-translation:'.$request->user()->getAuthIdentifier();
            abort_if(RateLimiter::tooManyAttempts($rateKey, 6), 429);
            RateLimiter::hit($rateKey, 60);
            $value = $drafts->draft($source, $locales->default(), $data['locale']);
            $audit->record('theme.translation_drafted', null, ['theme' => $theme->name, 'locale' => $data['locale'], 'key' => $data['key']]);

            return response()->json(['value' => $value]);
        }
        if ($data['operation'] === 'save') {
            $content->saveEditor($data['locale'], $source, $data['value'] ?? null);
            $audit->record('theme.translation_saved', null, ['theme' => $theme->name, 'locale' => $data['locale'], 'key' => $data['key']]);
        }

        return response()->json(['value' => $content->editorValue($data['locale'], $source) ?? '']);
    }
}
