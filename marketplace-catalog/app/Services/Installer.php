<?php

namespace Marketplace\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use PDOException;

/**
 * First-run setup from the browser: checks a MySQL/MariaDB connection,
 * saves it in .env, creates the tables, and marks the app installed with a
 * lock file so the installer never opens again.
 */
class Installer
{
    public const MYSQL_MIN = '5.7.0';

    public const MARIADB_MIN = '10.3.0';

    public function isInstalled(): bool
    {
        return is_file((string) config('marketplace.installed_file'));
    }

    public function markInstalled(): void
    {
        $file = (string) config('marketplace.installed_file');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, now()->toIso8601String()."\n");
    }

    /**
     * Makes sure .env exists and has an APP_KEY, so sessions and encrypted
     * cookies work on the very first request.
     *
     * @throws InstallerException
     */
    public function prepareEnvironment(): void
    {
        $env = app()->environmentFilePath();

        if (! is_file($env) && is_file(base_path('.env.example'))) {
            if (! @copy(base_path('.env.example'), $env)) {
                throw new InstallerException('Create a writable .env file in '.dirname($env).' (copy .env.example).');
            }
        }

        if ((string) config('app.key') === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $this->writeEnv(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        }
    }

    /**
     * Connects with these details and returns the server version.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     *
     * @throws InstallerException
     */
    public function connect(array $db): string
    {
        if (! extension_loaded('pdo_mysql')) {
            throw new InstallerException('PHP is missing the pdo_mysql extension. Enable it in your hosting control panel.');
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']),
                $db['username'],
                (string) ($db['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );

            return (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (PDOException $e) {
            throw new InstallerException('Could not connect to the database: '.$e->getMessage());
        }
    }

    /**
     * Checks the server, saves the connection in .env and creates the tables.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return string the driver: mysql or mariadb
     *
     * @throws InstallerException
     */
    public function setUpDatabase(array $db): string
    {
        $version = $this->connect($db);
        $driver = $this->driverFor($version);

        $this->writeEnv([
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) (int) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            'APP_URL' => rtrim(url('/'), '/'),
        ]);
        $this->useDatabase($db, $driver);

        try {
            if (app()->configurationIsCached()) {
                Artisan::call('config:clear'); // Later requests must read the new .env.
            }

            Artisan::call('migrate', ['--force' => true, '--seed' => true]);
        } catch (\Throwable $e) {
            throw new InstallerException('The tables could not be created: '.$e->getMessage());
        }

        return $driver;
    }

    /** @throws InstallerException */
    public function driverFor(string $version): string
    {
        $mariadb = stripos($version, 'mariadb') !== false;
        preg_match('/^(\d+\.\d+\.\d+)/', $version, $m);
        $number = $m[1] ?? '0.0.0';
        $minimum = $mariadb ? self::MARIADB_MIN : self::MYSQL_MIN;

        if (version_compare($number, $minimum, '<')) {
            throw new InstallerException(($mariadb ? 'MariaDB' : 'MySQL')." {$number} is too old; version {$minimum} or newer is required.");
        }

        return $mariadb ? 'mariadb' : 'mysql';
    }

    /**
     * Sets or adds KEY=value lines in .env, keeping everything else.
     *
     * @param  array<string, string>  $values
     *
     * @throws InstallerException
     */
    public function writeEnv(array $values): void
    {
        $path = app()->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.self::envValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $contents = preg_match($pattern, $contents) === 1
                ? preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new InstallerException("The installer cannot write {$path}. Make the file writable, then try again.");
        }
    }

    /** A .env value that reads back exactly (quotes, $, #, backslashes). */
    public static function envValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    /** Points this request at the new connection so migrations run there. */
    protected function useDatabase(array $db, string $driver): void
    {
        config([
            "database.connections.{$driver}.host" => $db['host'],
            "database.connections.{$driver}.port" => (int) $db['port'],
            "database.connections.{$driver}.database" => $db['database'],
            "database.connections.{$driver}.username" => $db['username'],
            "database.connections.{$driver}.password" => (string) ($db['password'] ?? ''),
            'database.default' => $driver,
        ]);
        DB::purge($driver);
    }
}
