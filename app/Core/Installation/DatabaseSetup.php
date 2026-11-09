<?php

namespace App\Core\Installation;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

/**
 * The manual installer's database step, with no shell access: tests a
 * MySQL/MariaDB connection, saves it in .env, then migrates and seeds
 * through Artisan from PHP.
 */
class DatabaseSetup
{
    public const MYSQL_MIN = '5.7.0';

    public const MARIADB_MIN = '10.3.0';

    /**
     * Makes sure .env exists and has an APP_KEY, so sessions and encrypted
     * cookies work on the very first request of a fresh upload.
     *
     * @throws InstallerException
     */
    public function prepareEnvironment(): void
    {
        $env = app()->environmentFilePath();
        $example = base_path('.env.example');

        if (! is_file($env)) {
            // Git platforms inject the configuration as environment variables:
            // with an APP_KEY already set there is nothing to prepare.
            if ((string) config('app.key') !== '') {
                return;
            }

            if (! is_file($example) || ! @copy($example, $env)) {
                throw new InstallerException('Create a writable .env file in the application folder (copy .env.example to .env).');
            }

            // .env.example is a development template; a public site must not run in debug mode.
            // Literal: APP_DEBUG must stay the boolean keyword, not the string "false".
            $this->writeEnv(['APP_ENV' => 'production', 'APP_DEBUG' => 'false'], literal: true);
        }

        if ((string) config('app.key') === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $this->writeEnv(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        }
    }

    /**
     * Connects with these details and returns the driver (mysql or mariadb)
     * and the server version.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return array{driver: string, version: string}
     *
     * @throws InstallerException
     */
    public function test(array $db): array
    {
        $version = $this->connect($db);

        return ['driver' => $this->driverFor($version), 'version' => $version];
    }

    /**
     * Tests the connection, saves it in .env, creates the tables and seeds
     * the permissions and system roles.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return array{driver: string, storage_linked: bool}
     *
     * @throws InstallerException
     */
    public function apply(array $db): array
    {
        $driver = $this->test($db)['driver'];
        $password = (string) ($db['password'] ?? '');

        $this->writeEnv([
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) (int) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $password,
            'APP_URL' => rtrim(url('/'), '/'),
        ]);

        $this->useDatabase($db, $driver);

        try {
            if (app()->configurationIsCached()) {
                Artisan::call('config:clear'); // Later requests must read the new .env.
            }

            Artisan::call('migrate', ['--force' => true, '--seed' => true]);
        } catch (Throwable $e) {
            throw new InstallerException('The tables could not be created: '.$this->redact($e->getMessage(), $password));
        }

        return ['driver' => $driver, 'storage_linked' => $this->linkStorage()];
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
     * Sets or adds KEY=value lines in .env, keeping everything else. Values
     * are escaped to read back exactly; $literal writes installer-chosen
     * keywords such as false as-is (letters, digits and _ only).
     *
     * @param  array<string, string>  $values
     *
     * @throws InstallerException
     */
    public function writeEnv(array $values, bool $literal = false): void
    {
        $path = app()->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            if (preg_match('/[\r\n\0]/', $value) === 1) {
                throw new InstallerException("{$key} cannot contain line breaks.");
            }

            if ($literal && preg_match('/^\w+$/', $value) !== 1) {
                throw new InstallerException("{$key} is not a plain keyword.");
            }

            $line = $key.'='.($literal ? $value : self::envValue($value));
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        if (! is_dir(dirname($path)) || @file_put_contents($path, ltrim($contents, "\n"), LOCK_EX) === false) {
            throw new InstallerException('The installer cannot write the .env file. Make it writable by the web server, then try again.');
        }
    }

    /**
     * A .env value that reads back exactly through env(): quotes, $, #, spaces
     * and backslashes, and values env() would otherwise reinterpret (true,
     * false, null, empty, or a value wrapped in its own quotes), which get one
     * extra pair of quotes for env() to strip.
     */
    public static function envValue(string $value): string
    {
        if (preg_match('/\A(\(?(true|false|null|empty)\)?|([\'"]).*\3)\z/is', $value) === 1) {
            $value = '"'.$value.'"';
        } elseif ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    /**
     * Opens a PDO connection and returns the server version.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     *
     * @throws InstallerException
     */
    protected function connect(array $db): string
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

            $statement = $pdo->query('SELECT VERSION()');
            $version = $statement === false ? false : $statement->fetchColumn();

            if (! is_string($version)) {
                throw new InstallerException('Connected, but the database server did not report its version.');
            }

            return $version;
        } catch (PDOException $e) {
            throw new InstallerException('Could not connect to the database: '.$this->redact($e->getMessage(), (string) ($db['password'] ?? '')));
        }
    }

    /** Never show the submitted password, even when the driver echoes it. */
    protected function redact(string $message, string $password): string
    {
        return $password === '' ? $message : str_replace($password, '********', $message);
    }

    /**
     * Points this request at the new connection so migrations run there.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     */
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

    /** public/storage serves uploaded media; some shared hosts disable symlinks. */
    protected function linkStorage(): bool
    {
        if (file_exists(public_path('storage'))) {
            return true;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            report($e);
        }

        return file_exists(public_path('storage'));
    }
}
