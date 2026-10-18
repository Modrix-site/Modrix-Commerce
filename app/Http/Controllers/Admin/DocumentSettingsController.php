<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Documents\DocumentPreferences;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DocumentSettingsController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect('/admin/settings/store#documents');
    }

    public function update(Request $request, DocumentPreferences $preferences): RedirectResponse
    {
        try {
            DB::transaction(fn () => $preferences->save($request));
        } finally {
            app(SettingsRepository::class)->flush();
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document settings saved.')]);

        return redirect('/admin/settings/store#documents');
    }
}
