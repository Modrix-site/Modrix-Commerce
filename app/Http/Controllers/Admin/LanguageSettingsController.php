<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Localization\Locales;
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
    public function edit(Locales $locales): Response
    {
        return Inertia::render('admin/settings/languages', [
            'available' => Locales::options(array_keys(Locales::AVAILABLE)),
            'default' => $locales->default(),
            'additional' => $locales->additional(),
        ]);
    }

    public function update(Request $request, Locales $locales, AuditLogger $audit): RedirectResponse
    {
        $codes = array_keys(Locales::AVAILABLE);
        $data = $request->validate([
            'default' => ['required', Rule::in($codes)],
            'additional' => ['array', 'max:'.count($codes)],
            'additional.*' => [Rule::in($codes)],
        ]);

        $before = ['default' => $locales->default(), 'additional' => $locales->additional()];
        $locales->update($data['default'], array_values($data['additional'] ?? []));
        $audit->record('settings.languages_updated', null, ['from' => $before, 'to' => ['default' => $locales->default(), 'additional' => $locales->additional()]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Languages saved. Existing translations are kept even when a language is turned off.']);

        return back();
    }
}
