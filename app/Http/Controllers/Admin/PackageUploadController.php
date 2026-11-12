<?php

namespace App\Http\Controllers\Admin;

use App\Core\Extensions\UploadedPackage;
use App\Core\Marketplace\PackageException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class PackageUploadController extends Controller
{
    public function theme(Request $request, UploadedPackage $packages): RedirectResponse
    {
        return $this->receive($request, $packages, 'theme');
    }

    public function module(Request $request, UploadedPackage $packages): RedirectResponse
    {
        return $this->receive($request, $packages, 'module');
    }

    private function receive(Request $request, UploadedPackage $packages, string $type): RedirectResponse
    {
        $request->validate(['package' => ['required', 'file', 'mimes:zip', 'max:51200']]);
        $file = $request->file('package');
        abort_unless($file instanceof UploadedFile, 422);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        try {
            $name = $packages->receive($file, $type, $actor);
        } catch (PackageException $e) {
            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $type === 'theme'
            ? "{$name} uploaded. Preview or activate it from Appearance."
            : "{$name} uploaded. Review its details before installing and enabling it. Modules with frontend pages also need an application asset build."]);

        return redirect()->route($type === 'theme' ? 'admin.themes.index' : 'admin.extensions.index');
    }
}
