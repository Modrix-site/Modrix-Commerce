<?php

namespace App\Http\Controllers\Install;

use App\Core\Installation\InstallerToken;
use App\Core\Installation\InstallPlatform;
use App\Core\Installation\RequirementChecker;
use App\Core\Settings\Currencies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Install\InstallRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstallController extends Controller
{
    public function show(RequirementChecker $requirements, InstallerToken $token): Response
    {
        // Create the token file if needed. Its value is deliberately never sent to the browser.
        $token->ensure();

        $checks = $requirements->check();

        return Inertia::render('install/index', [
            'checks' => $checks,
            'ready' => RequirementChecker::allPassed($checks),
            'currencies' => collect(Currencies::ALL)->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name']])->values(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => config('app.timezone'),
            'tokenLocation' => 'storage/app/private/installer.token',
        ]);
    }

    public function store(
        InstallRequest $request,
        InstallerToken $token,
        RequirementChecker $requirements,
        InstallPlatform $install,
    ): RedirectResponse {
        if (! $token->verify($request->string('setup_token')->toString())) {
            throw ValidationException::withMessages(['setup_token' => 'The setup token is invalid.']);
        }

        if (! RequirementChecker::allPassed($requirements->check())) {
            throw ValidationException::withMessages(['setup_token' => 'Resolve the failing requirements before installing.']);
        }

        /** @var array{name: string, email: string, password: string, store_name: string, default_currency: string, timezone: string} $data */
        $data = $request->safe()->except(['setup_token', 'password_confirmation']);

        $owner = $install->handle($data);

        Auth::login($owner);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installation complete. Welcome to your store.')]);

        return redirect()->route('admin.dashboard');
    }
}
