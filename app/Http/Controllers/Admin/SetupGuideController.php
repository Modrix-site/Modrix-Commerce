<?php

namespace App\Http\Controllers\Admin;

use App\Core\Onboarding\SetupGuide;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The dashboard's setup guide: skip optional steps and hide or show the guide.
 */
class SetupGuideController extends Controller
{
    public function skip(Request $request, SetupGuide $guide, string $step): RedirectResponse
    {
        abort_unless(in_array($step, $guide->keys(), true) && $guide->isOptional($step), 404);

        $data = $request->validate(['skipped' => ['required', 'boolean']]);
        $guide->skip($step, (bool) $data['skipped']);

        return back();
    }

    public function visibility(Request $request, SetupGuide $guide): RedirectResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $guide->hide((bool) $data['hidden']);

        return back();
    }
}
