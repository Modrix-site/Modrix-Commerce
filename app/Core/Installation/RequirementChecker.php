<?php

namespace App\Core\Installation;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Environment checks shown by the installer and the system status page.
 * Results never include secrets, credentials or absolute filesystem paths.
 */
final class RequirementChecker
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring',
        'openssl', 'pdo', 'session', 'tokenizer', 'xml', 'zip', 'intl',
    ];

    public function __construct(private readonly Migrator $migrator) {}

    /**
     * @return list<array{key: string, label: string, passed: bool, detail: string}>
     */
    public function check(): array
    {
        $checks = $this->serverChecks();
        $checks[] = $this->databaseCheck();
        $checks[] = $this->migrationCheck();

        return $checks;
    }

    /**
     * PHP version, extensions and writable directories.
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string}>
     */
    private function serverChecks(): array
    {
        $checks = [
            [
                'key' => 'php',
                'label' => 'PHP '.self::MIN_PHP.' or newer',
                'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
                'detail' => 'Running '.PHP_VERSION,
            ],
        ];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'key' => "ext.{$extension}",
                'label' => "PHP extension: {$extension}",
                'passed' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Loaded' : 'Missing',
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $checks[] = [
                'key' => "writable.{$label}",
                'label' => "Writable directory: {$label}",
                'passed' => is_dir($path) && is_writable($path),
                'detail' => is_writable($path) ? 'Writable' : 'Not writable by the web server user',
            ];
        }

        return $checks;
    }

    /**
     * Checks before the manual installer's database step: the server checks
     * plus what the step itself needs.
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string}>
     */
    public function manualInstallChecks(bool $servedFromPublic): array
    {
        $checks = $this->serverChecks();

        $checks[] = [
            'key' => 'ext.pdo_mysql',
            'label' => 'PHP extension: pdo_mysql',
            'passed' => extension_loaded('pdo_mysql'),
            'detail' => extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing. Enable it in your hosting control panel.',
        ];

        $env = app()->environmentFilePath();
        $envWritable = is_file($env) ? is_writable($env) : is_writable(dirname($env));
        $checks[] = [
            'key' => 'writable.env',
            'label' => 'Writable file: .env',
            'passed' => $envWritable,
            'detail' => $envWritable ? 'Writable' : 'Not writable by the web server user',
        ];

        $checks[] = [
            'key' => 'docroot',
            'label' => 'Document root is the public/ folder',
            'passed' => $servedFromPublic,
            'detail' => $servedFromPublic ? 'OK' : 'The site is served from the project folder. Point the document root at public/.',
        ];

        return $checks;
    }

    /**
     * @param  list<array{passed: bool}>  $checks
     */
    public static function allPassed(array $checks): bool
    {
        return collect($checks)->every(fn (array $check) => $check['passed']);
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            $passed = true;
            $detail = 'Connected ('.DB::connection()->getDriverName().')';
        } catch (Throwable) {
            $passed = false;
            $detail = 'Cannot connect. Check the DB_* values in .env.';
        }

        return ['key' => 'database', 'label' => 'Database connection', 'passed' => $passed, 'detail' => $detail];
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string}
     */
    public function migrationCheck(): array
    {
        $pending = $this->pendingMigrations();

        return [
            'key' => 'migrations',
            'label' => 'Database migrations applied',
            'passed' => $pending === 0,
            'detail' => match (true) {
                $pending === null => 'Unable to inspect migrations',
                $pending === 0 => 'Up to date',
                default => "{$pending} pending. Run: php artisan migrate --force",
            },
        ];
    }

    public function pendingMigrations(): ?int
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return count($this->migrator->getMigrationFiles($this->paths()));
            }

            $ran = $this->migrator->getRepository()->getRan();
            $files = array_keys($this->migrator->getMigrationFiles($this->paths()));

            return count(array_diff($files, $ran));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return array_values(array_merge([database_path('migrations')], $this->migrator->paths()));
    }
}
