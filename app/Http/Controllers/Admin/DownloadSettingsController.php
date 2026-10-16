<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Downloads\DownloadSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Store → Digital downloads: limits for new download links.
 */
class DownloadSettingsController extends Controller
{
    public function update(Request $request, DownloadSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'download_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'download_expiry_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        $settings->save((int) $data['download_limit'], (int) $data['download_expiry_days']);
        $audit->record('settings.downloads_updated', null, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Download settings saved.')]);

        return back();
    }
}
