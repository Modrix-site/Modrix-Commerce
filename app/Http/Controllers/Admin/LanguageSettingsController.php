<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Localization\ContentTranslations;
use App\Core\Localization\Locales;
use App\Core\Settings\SettingsRepository;
use App\Core\Themes\ThemeResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront languages: the default (the language product and category
 * texts are written in) and the additional languages shoppers can choose.
 */
class LanguageSettingsController extends Controller
{
    public function edit(Locales $locales, ContentTranslations $content, ThemeResolver $themes): Response
    {
        return Inertia::render('admin/settings/languages', [
            'available' => Locales::adminOptions(array_keys(Locales::AVAILABLE)),
            'default' => $locales->default(),
            'additional' => $locales->additional(),
            'mode' => $content->mode(),
            'phrases' => $content->phrases($themes->forRequest(request())),
            'translations' => $content->manual(),
        ]);
    }

    public function update(Request $request, Locales $locales, ContentTranslations $content, AuditLogger $audit): RedirectResponse
    {
        $codes = array_keys(Locales::AVAILABLE);
        $data = $request->validate([
            'default' => ['required', Rule::in($codes)],
            'additional' => ['array', 'max:'.count($codes)],
            'additional.*' => [Rule::in($codes)],
            'mode' => ['required', Rule::in([ContentTranslations::MODE_AUTOMATIC, ContentTranslations::MODE_MANUAL])],
            'translations' => ['array', 'max:2000'],
            'translations.*.locale' => ['required', 'string'],
            'translations.*.source' => ['required', 'string', 'max:500'],
            'translations.*.value' => ['nullable', 'string', 'max:500'],
        ]);

        $before = ['default' => $locales->default(), 'additional' => $locales->additional(), 'mode' => $content->mode()];
        $locales->update($data['default'], array_values($data['additional'] ?? []));
        app(SettingsRepository::class)->set(ContentTranslations::KEY, $data['mode']);
        if ($data['mode'] === ContentTranslations::MODE_MANUAL) {
            $content->saveManual($data['translations'] ?? []);
        }
        $audit->record('settings.languages_updated', null, ['from' => $before, 'to' => ['default' => $locales->default(), 'additional' => $locales->additional(), 'mode' => $data['mode']]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Languages saved. Existing translations are kept even when a language is turned off.']);

        return back();
    }
}
