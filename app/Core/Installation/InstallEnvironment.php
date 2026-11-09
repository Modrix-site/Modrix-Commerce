<?php

namespace App\Core\Installation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Decides how the installer runs. ECOMODULAR_INSTALL_MODE=auto|manual forces
 * a mode; the default (detect) chooses auto when the default connection is
 * configured, reachable and fully migrated.
 */
final class InstallEnvironment
{
    public const NOT_CONFIGURED = 'not_configured';

    public const UNREACHABLE = 'unreachable';

    public const NOT_MIGRATED = 'not_migrated';

    public function __construct(private readonly RequirementChecker $requirements) {}

    public function mode(): InstallMode
    {
        return match (strtolower((string) config('ecomodular.installer.mode', 'detect'))) {
            'auto' => InstallMode::Auto,
            'manual' => InstallMode::Manual,
            default => $this->databaseReady() ? InstallMode::Auto : InstallMode::Manual,
        };
    }

    public function databaseReady(): bool
    {
        return $this->databaseProblem() === null;
    }

    /**
     * Why the database cannot be used yet, or null when it is ready.
     * Not cached: the database step changes the answer within one request.
     */
    public function databaseProblem(): ?string
    {
        $name = (string) config('database.default');
        $connection = (array) config("database.connections.{$name}", []);

        if (trim((string) ($connection['driver'] ?? '')) === '' || trim((string) ($connection['database'] ?? '')) === '') {
            return self::NOT_CONFIGURED;
        }

        try {
            DB::connection($name)->getPdo();
        } catch (Throwable) {
            return self::UNREACHABLE;
        }

        try {
            if (! DB::connection($name)->getSchemaBuilder()->hasTable('migrations')) {
                return self::NOT_MIGRATED;
            }
        } catch (Throwable) {
            return self::UNREACHABLE;
        }

        return $this->requirements->pendingMigrations() === 0 ? null : self::NOT_MIGRATED;
    }

    /**
     * True when the request reached the app through public/index.php and the
     * web server's document root does not contain the project folder, so
     * .env, storage/ and vendor/ cannot be downloaded. Uploading the project
     * into public_html and browsing to /public/install fails the second test.
     * An unknown document root fails closed.
     */
    public function servedFromPublic(Request $request): bool
    {
        $script = $request->server->get('SCRIPT_FILENAME');
        $documentRoot = $request->server->get('DOCUMENT_ROOT');

        if (! is_string($script) || $script === '' || ! is_string($documentRoot) || $documentRoot === '') {
            return false;
        }

        $directory = realpath(dirname($script));
        $public = realpath(public_path());
        $root = realpath($documentRoot);
        $project = realpath(base_path());

        if ($directory === false || $public === false || $root === false || $project === false || $directory !== $public) {
            return false;
        }

        $root = rtrim($root, '\\/').DIRECTORY_SEPARATOR;

        return ! str_starts_with(rtrim($project, '\\/').DIRECTORY_SEPARATOR, $root);
    }

    /** The configured database password: proof of ownership when the database step is skipped. */
    public function databasePasswordConfigured(): string
    {
        return (string) config('database.connections.'.config('database.default').'.password', '');
    }
}
