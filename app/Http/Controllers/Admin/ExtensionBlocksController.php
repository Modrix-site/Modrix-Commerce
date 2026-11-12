<?php

namespace App\Http\Controllers\Admin;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Extensions\TabSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customizer: where extension blocks show (store-wide) and the extension
 * settings shown in page tabs.
 */
class ExtensionBlocksController extends Controller
{
    public function update(Request $request, BlockPlacements $placements, StorefrontSlots $slots): RedirectResponse
    {
        $blocks = $request->input('blocks');
        $placements->save(is_array($blocks) ? $blocks : [], $slots);

        return back()->with('success', 'Extension blocks saved.');
    }

    public function settings(Request $request, TabSettings $settings, string $module): RedirectResponse
    {
        $input = $request->input('settings');
        $user = $request->user();
        $settings->save($module, is_array($input) ? $input : [], $user instanceof User ? $user : null);

        return back()->with('success', 'Extension settings saved.');
    }
}
