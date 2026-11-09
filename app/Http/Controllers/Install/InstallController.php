<?php

namespace App\Http\Controllers\Install;

use App\Core\Installation\DatabaseSetup;
use App\Core\Installation\InstallEnvironment;
use App\Core\Installation\InstallerException;
use App\Core\Installation\InstallerToken;
use App\Core\Installation\InstallMode;
use App\Core\Installation\InstallPlatform;
use App\Core\Installation\RequirementChecker;
use App\Core\Settings\Currencies;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PrepareInstallEnvironment;
use App\Http\Requests\Install\DatabaseRequest;
use App\Http\Requests\Install\InstallRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One wizard, two modes. Auto (Git deploys that provide a migrated database):
 * requirements, then the owner. Manual (shared hosting): requirements and
 * document root, the database step, then the owner.
 */
class InstallController extends Controller
{
    private const VERIFIED = 'install.verified';

    public function __construct(
        private readonly InstallEnvironment $environment,
        private readonly RequirementChecker $requirements,
        private readonly InstallerToken $token,
    ) {}

    public function show(Request $request): Response
    {
        // Create the token file if needed. Its value is deliberately never sent to the browser.
        $this->token->ensure();

        $mode = $this->environment->mode();
        $databaseReady = $this->environment->databaseReady();
        $servedFromPublic = $this->environment->servedFromPublic($request);

        if ($mode === InstallMode::Auto) {
            $step = $databaseReady ? 'owner' : 'requirements';
            $checks = $this->requirements->check();
        } elseif ($databaseReady && $request->session()->get(self::VERIFIED) === true) {
            $step = 'owner';
            $checks = $this->requirements->check();
        } else {
            $checks = $this->requirements->manualInstallChecks($servedFromPublic);
            $step = RequirementChecker::allPassed($checks) ? 'database' : 'requirements';
        }

        return Inertia::render('install/index', [
            'mode' => $mode->value,
            'step' => $step,
            'checks' => $checks,
            'ready' => RequirementChecker::allPassed($checks),
            'databaseProblem' => $this->environment->databaseProblem(),
            'servedFromPublic' => $servedFromPublic,
            'proof' => $this->proof($request),
            'connectionTest' => $request->session()->get('install.connection'),
            'storageLinked' => $request->session()->get('install.storage_linked'),
            'currencies' => collect(Currencies::ALL)->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name']])->values(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => config('app.timezone'),
            'tokenLocation' => 'storage/app/private/installer.token',
        ]);
    }

    /**
     * Manual mode: try the connection details without saving them.
     */
    public function testDatabase(DatabaseRequest $request, DatabaseSetup $setup): RedirectResponse
    {
        $this->ensureManualDatabaseStep($request);

        try {
            $server = $setup->test($request->connection());
            $version = preg_replace('/-.*$/', '', $server['version']);
            $result = ['ok' => true, 'message' => 'Connected to '.($server['driver'] === 'mariadb' ? 'MariaDB' : 'MySQL')." {$version}."];
        } catch (InstallerException $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        return back()->with('install.connection', $result);
    }

    /**
     * Manual mode: save the connection in .env, create the tables and seed
     * permissions and system roles, then continue to the owner step.
     */
    public function database(DatabaseRequest $request, DatabaseSetup $setup): RedirectResponse
    {
        $this->ensureManualDatabaseStep($request);

        if (! RequirementChecker::allPassed($this->requirements->manualInstallChecks($this->environment->servedFromPublic($request)))) {
            throw ValidationException::withMessages(['host' => 'Resolve the failing requirements before connecting the database.']);
        }

        try {
            $result = $setup->apply($request->connection());
        } catch (InstallerException $e) {
            throw ValidationException::withMessages(['host' => $e->getMessage()]);
        }

        // New session id once ownership is proven: a planted session cookie gains nothing.
        $request->session()->regenerate();
        $request->session()->put(self::VERIFIED, true);

        return redirect()->route('install.show')->with('install.storage_linked', $result['storage_linked']);
    }

    public function store(InstallRequest $request, InstallPlatform $install): RedirectResponse
    {
        $this->verifyProof($request);

        if (! RequirementChecker::allPassed($this->requirements->check())) {
            throw ValidationException::withMessages(['store_name' => 'Resolve the failing requirements before installing.']);
        }

        /** @var array{name: string, email: string, password: string, store_name: string, default_currency: string, timezone: string} $data */
        $data = $request->safe()->except(['setup_token', 'database_password', 'password_confirmation']);

        $owner = $install->handle($data);

        // Sessions were kept in files until now; continue in the configured store
        // so the owner is still signed in on the next (installed) request.
        $driver = $request->attributes->get(PrepareInstallEnvironment::SESSION_DRIVER);
        if (is_string($driver)) {
            config(['session.driver' => $driver]);
            $request->session()->setHandler(app('session')->driver($driver)->getHandler());
        }

        $request->session()->forget(self::VERIFIED);
        Auth::login($owner);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installation complete. Welcome to your store.')]);

        return redirect()->route('admin.dashboard');
    }

    /**
     * How the merchant proves they own this server: the database step done
     * in this session, the configured database password (auto-deploy), or
     * the setup token file.
     */
    private function proof(Request $request): string
    {
        return match (true) {
            $request->session()->get(self::VERIFIED) === true && $this->environment->databaseReady() => 'session',
            $this->environment->databasePasswordConfigured() !== '' => 'database_password',
            default => 'setup_token',
        };
    }

    private function verifyProof(InstallRequest $request): void
    {
        match ($this->proof($request)) {
            'session' => null,
            'database_password' => hash_equals($this->environment->databasePasswordConfigured(), (string) $request->input('database_password', ''))
                ?: throw ValidationException::withMessages(['database_password' => 'The database password is incorrect.']),
            default => $this->verifyToken((string) $request->input('setup_token', '')),
        };
    }

    private function verifyToken(string $candidate): void
    {
        if (! $this->token->verify($candidate)) {
            throw ValidationException::withMessages(['setup_token' => $this->token->expired()
                ? 'The setup token has expired. Reload this page to create a new one, then read it from the server again.'
                : 'The setup token is invalid.']);
        }
    }

    private function ensureManualDatabaseStep(DatabaseRequest $request): void
    {
        abort_unless($this->environment->mode() === InstallMode::Manual, 404); // Also checked in DatabaseRequest.

        $this->verifyToken($request->string('setup_token')->toString());
    }
}
