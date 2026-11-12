<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Platform;
use App\Core\Updates\CoreReleaseVerifier;
use App\Core\Updates\CoreUpdateCheck;
use App\Core\Updates\CoreUpdateLauncher;
use App\Core\Updates\CoreUpdateRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Throwable;

/**
 * Check for, start and follow Modrix Commerce core platform updates.
 */
class CoreUpdateController extends Controller
{
    public function check(CoreUpdateCheck $check): RedirectResponse
    {
        $check->run();

        return back();
    }

    public function start(Request $request, CoreUpdateCheck $check, CoreReleaseVerifier $verifier, CoreUpdateLauncher $launcher, AuditLogger $audit): RedirectResponse
    {
        $item = $check->item();
        $verified = $item === null ? null : $verifier->verify($item);

        if ($verified === null) {
            return back()->withErrors(['core_update' => 'There is no verified update to install. Check for updates first.']);
        }

        // The check and the insert must not interleave with another start.
        $guard = Cache::lock('core-update-start', 30);
        if (! $guard->get()) {
            return back()->withErrors(['core_update' => 'A core update is already in progress.']);
        }

        try {
            if (CoreUpdateRun::live() !== null) {
                return back()->withErrors(['core_update' => 'A core update is already in progress.']);
            }

            $run = CoreUpdateRun::query()->forceCreate([
                'from_version' => Platform::VERSION,
                'to_version' => $verified['version'],
                'release' => $item,
                'status' => CoreUpdateRun::QUEUED,
                'actor_id' => $request->user()->getKey(),
            ]);
        } finally {
            $guard->release();
        }

        $audit->record('core.update_started', $run, [
            'run_id' => $run->getKey(),
            'from_version' => $run->from_version,
            'to_version' => $run->to_version,
        ]);

        try {
            $launcher->launch($run);
        } catch (Throwable $e) {
            report($e);
            $run->forceFill(['status' => CoreUpdateRun::FAILED, 'message' => 'The update could not be started: '.$e->getMessage()])->save();

            return back()->withErrors(['core_update' => $run->message]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Updating to Modrix Commerce {$run->to_version}."]);

        return back();
    }

    public function show(CoreUpdateRun $run): JsonResponse
    {
        return response()->json([
            'status' => $run->status,
            'message' => $run->message,
            'to_version' => $run->to_version,
            'hint' => $run->startHint(),
        ]);
    }
}
