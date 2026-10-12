<?php

namespace App\Core\Operations;

use App\Core\Platform;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Self-contained backups: a database dump plus the files a store cannot
 * rebuild from the repository (media, private uploads, installed modules
 * and themes), with a manifest of SHA-256 checksums. Archives can be
 * AES-256 encrypted. Restores verify every checksum before changing
 * anything and keep the replaced files until the restore succeeds.
 *
 * Not included: .env (APP_KEY decrypts stored credentials; keep it in a
 * secret manager), vendor/, node_modules/ and built assets.
 */
final class Backups
{
    public const FORMAT = 1;

    /** Archive prefix => directory relative to root(). */
    private const FILE_SETS = [
        'files/storage-public' => 'storage/app/public',
        'files/storage-private' => 'storage/app/private',
        'files/modules' => 'modules',
        'files/themes' => 'themes',
    ];

    /** Paths under storage/app/private that are never backed up. */
    private const PRIVATE_EXCLUDES = ['backups', 'marketplace/tmp'];

    /** Base directory the file sets are relative to (the application root). */
    public function root(): string
    {
        return rtrim((string) config('ecomodular.backup.files_root', base_path()), '/\\');
    }

    public function directory(): string
    {
        return (string) config('ecomodular.backup.path', storage_path('app/private/backups'));
    }

    /**
     * @return array{name: string, path: string, size: int, encrypted: bool}
     */
    public function create(bool $withFiles = true): array
    {
        File::ensureDirectoryExists($this->directory(), 0700);
        $work = storage_path('framework/backup-'.Str::random(12));
        File::ensureDirectoryExists($work, 0700);

        try {
            $dump = $work.'/database.'.$this->dumpExtension();
            $this->dumpDatabase($dump);

            $entries = ['database/'.basename($dump) => $dump];

            if ($withFiles) {
                foreach (self::FILE_SETS as $prefix => $relative) {
                    foreach ($this->filesIn($this->root().'/'.$relative, $relative === 'storage/app/private') as $file => $name) {
                        $entries["{$prefix}/{$name}"] = $file;
                    }
                }
            }

            $name = 'ecomodular-backup-'.now()->utc()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
            $path = $this->directory().'/'.$name;
            $password = $this->password();

            $manifest = [
                'format' => self::FORMAT,
                'platform_version' => Platform::VERSION,
                'created_at' => now()->utc()->toIso8601String(),
                'database_driver' => DB::connection()->getDriverName(),
                'includes_files' => $withFiles,
                'files' => array_map(fn (string $file) => hash_file('sha256', $file), $entries),
            ];

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Cannot create the backup archive.');
            }

            $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            foreach ($entries as $entry => $file) {
                $zip->addFile($file, $entry);
            }

            if ($password !== null) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256, $password);
                }
            }

            if (! $zip->close()) {
                @unlink($path);

                throw new RuntimeException('Writing the backup archive failed.');
            }

            @chmod($path, 0600);
            $this->prune();

            return ['name' => $name, 'path' => $path, 'size' => (int) filesize($path), 'encrypted' => $password !== null];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * @return list<array{name: string, path: string, size: int, created_at: int}>
     */
    public function all(): array
    {
        $files = glob($this->directory().'/ecomodular-backup-*.zip') ?: [];
        $list = array_map(fn (string $path) => [
            'name' => basename($path),
            'path' => $path,
            'size' => (int) filesize($path),
            'created_at' => (int) filemtime($path),
        ], $files);

        usort($list, fn (array $a, array $b) => $b['created_at'] <=> $a['created_at'] ?: strcmp($b['name'], $a['name']));

        return $list;
    }

    /**
     * @return array{name: string, path: string, size: int, created_at: int}|null
     */
    public function latest(): ?array
    {
        return $this->all()[0] ?? null;
    }

    /**
     * Verify an archive without changing anything.
     *
     * @return array<string, mixed> The manifest.
     */
    public function verify(string $path): array
    {
        $work = storage_path('framework/restore-'.Str::random(12));

        try {
            return $this->extract($path, $work);
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Replace the database and files with the archive's content. The caller
     * puts the application in maintenance mode around this.
     *
     * @return array<string, mixed> The manifest.
     */
    public function restore(string $path): array
    {
        $work = storage_path('framework/restore-'.Str::random(12));

        try {
            $manifest = $this->extract($path, $work);

            if (($manifest['database_driver'] ?? null) !== DB::connection()->getDriverName()) {
                throw new RuntimeException('The backup was made with the '.($manifest['database_driver'] ?? '?').' driver; this store uses '.DB::connection()->getDriverName().'.');
            }

            $dump = glob($work.'/database/database.*')[0] ?? null;
            if ($dump === null) {
                throw new RuntimeException('The archive contains no database dump.');
            }

            // Files first: they can be put back if the database import fails.
            $aside = [];
            try {
                if ($manifest['includes_files'] ?? false) {
                    foreach (self::FILE_SETS as $prefix => $relative) {
                        $aside[$relative] = $this->swapDirectory($work.'/'.$prefix, $this->root().'/'.$relative, $relative === 'storage/app/private');
                    }
                }

                $this->importDatabase($dump);
            } catch (\Throwable $e) {
                foreach ($aside as $relative => $old) {
                    $this->unswapDirectory($this->root().'/'.$relative, $old, $relative === 'storage/app/private');
                }

                throw $e;
            }

            foreach ($aside as $old) {
                File::deleteDirectory($old);
            }

            // A backup from an older version is brought up to the current schema.
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('optimize:clear');

            return $manifest;
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function password(): ?string
    {
        $password = (string) config('ecomodular.backup.password', '');

        return $password === '' ? null : $password;
    }

    private function dumpExtension(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? 'sqlite' : 'sql';
    }

    private function dumpDatabase(string $target): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        match ($connection->getDriverName()) {
            // A consistent copy, also for in-memory and WAL databases.
            'sqlite' => $connection->statement('VACUUM INTO ?', [$target]),
            'mysql', 'mariadb' => $this->runWithDefaultsFile($config, (string) config('ecomodular.backup.mysqldump', 'mysqldump'), [
                '--single-transaction', '--quick', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
                '--result-file='.$target, (string) $config['database'],
            ]),
            'pgsql' => $this->runProcess([(string) config('ecomodular.backup.pg_dump', 'pg_dump'), '--clean', '--if-exists', '--no-owner', '--file='.$target,
                '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], (string) $config['database']], ['PGPASSWORD' => (string) $config['password']]),
            default => throw new RuntimeException('Backups support SQLite, MySQL, MariaDB and PostgreSQL.'),
        };

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('The database dump is empty.');
        }
    }

    private function importDatabase(string $dump): void
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        switch ($connection->getDriverName()) {
            case 'sqlite':
                $database = (string) $config['database'];
                if ($database === ':memory:' || $database === '') {
                    $this->importIntoOpenSqlite($dump);

                    return;
                }
                DB::disconnect();
                if (! copy($dump, $database)) {
                    throw new RuntimeException('Cannot write the database file.');
                }
                @unlink($database.'-wal');
                @unlink($database.'-shm');
                DB::reconnect();

                return;
            case 'mysql':
            case 'mariadb':
                $this->runWithDefaultsFile($config, (string) config('ecomodular.backup.mysql', 'mysql'), [(string) $config['database']], $dump);

                return;
            case 'pgsql':
                $this->runProcess([(string) config('ecomodular.backup.psql', 'psql'), '--quiet', '--set=ON_ERROR_STOP=1', '--file='.$dump,
                    '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], (string) $config['database']], ['PGPASSWORD' => (string) $config['password']]);

                return;
            default:
                throw new RuntimeException('Unsupported database driver.');
        }
    }

    /** In-memory SQLite (tests): copy every table from the dump into the open database. */
    private function importIntoOpenSqlite(string $dump): void
    {
        $connection = DB::connection();
        $connection->statement('PRAGMA foreign_keys = OFF');
        $connection->statement('ATTACH DATABASE ? AS restore_src', [$dump]);

        try {
            foreach ($connection->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $row) {
                $connection->statement('DROP TABLE IF EXISTS "'.$row->name.'"');
            }
            foreach ($connection->select("select name, sql from restore_src.sqlite_master where type = 'table' and name not like 'sqlite_%'") as $row) {
                $connection->statement($row->sql);
                $connection->statement('INSERT INTO "'.$row->name.'" SELECT * FROM restore_src."'.$row->name.'"');
            }
            foreach ($connection->select("select sql from restore_src.sqlite_master where type = 'index' and sql is not null") as $row) {
                $connection->statement($row->sql);
            }
        } finally {
            $connection->statement('DETACH DATABASE restore_src');
            $connection->statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * MySQL credentials go in a private options file, never on the command line.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $arguments
     */
    private function runWithDefaultsFile(array $config, string $binary, array $arguments, ?string $stdinFile = null): void
    {
        $defaults = tempnam(storage_path('framework'), 'my');
        if ($defaults === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        try {
            @chmod($defaults, 0600);
            $lines = ['[client]', 'user="'.addcslashes((string) $config['username'], '"\\').'"', 'password="'.addcslashes((string) $config['password'], '"\\').'"'];
            if (! empty($config['unix_socket'])) {
                $lines[] = 'socket="'.$config['unix_socket'].'"';
            } else {
                $lines[] = 'host="'.$config['host'].'"';
                $lines[] = 'port='.(int) $config['port'];
            }
            file_put_contents($defaults, implode("\n", $lines)."\n");

            $this->runProcess([$binary, '--defaults-extra-file='.$defaults, ...$arguments], [], $stdinFile);
        } finally {
            @unlink($defaults);
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    private function runProcess(array $command, array $env = [], ?string $stdinFile = null): void
    {
        $process = new Process($command, base_path(), $env === [] ? null : $env, null, (float) config('ecomodular.backup.timeout', 3600));

        if ($stdinFile !== null) {
            $stream = fopen($stdinFile, 'rb');
            $process->setInput($stream === false ? null : $stream);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            // The error output can name the host or user; keep it out of user-facing messages.
            logger()->error('Backup database command failed.', ['command' => basename($command[0]), 'exit' => $process->getExitCode(), 'error' => Str::limit($process->getErrorOutput(), 500)]);

            throw new RuntimeException(basename($command[0]).' failed (exit '.$process->getExitCode().'); see the application log.');
        }
    }

    /**
     * @return array<string, string> absolute file => relative name
     */
    private function filesIn(string $dir, bool $private): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));

            if ($private && $this->excluded($relative)) {
                continue;
            }

            $files[$file->getPathname()] = $relative;
        }

        return $files;
    }

    private function excluded(string $relative): bool
    {
        foreach (self::PRIVATE_EXCLUDES as $exclude) {
            if ($relative === $exclude || str_starts_with($relative, $exclude.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts entry by entry with path checks and verifies every checksum.
     *
     * @return array<string, mixed>
     */
    private function extract(string $path, string $work): array
    {
        $zip = new ZipArchive;
        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The backup archive cannot be opened.');
        }

        try {
            if ($password = $this->password()) {
                $zip->setPassword($password);
            }

            $raw = $zip->getFromName('manifest.json');
            if ($raw === false) {
                throw new RuntimeException('The archive has no readable manifest (wrong ECOMODULAR_BACKUP_PASSWORD?).');
            }

            $manifest = json_decode($raw, true);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('The backup manifest is not valid.');
            }

            File::ensureDirectoryExists($work, 0700);

            foreach ($manifest['files'] as $entry => $hash) {
                if (! is_string($entry) || ! $this->safeEntry($entry)) {
                    throw new RuntimeException('The archive contains an unsafe path.');
                }

                $stream = $zip->getStream($entry);
                if ($stream === false) {
                    throw new RuntimeException("Missing or unreadable entry: {$entry}");
                }

                $target = $work.'/'.$entry;
                File::ensureDirectoryExists(dirname($target));
                $out = fopen($target, 'wb');
                if ($out === false) {
                    throw new RuntimeException('Cannot write a temporary file.');
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);

                if (! hash_equals((string) $hash, (string) hash_file('sha256', $target))) {
                    throw new RuntimeException("Checksum mismatch for {$entry}: the archive is damaged or was modified.");
                }
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function safeEntry(string $entry): bool
    {
        if (str_contains($entry, '\\') || str_contains($entry, "\0") || str_starts_with($entry, '/') || preg_match('#(^|/)\.\.?(/|$)#', $entry) || preg_match('/^[A-Za-z]:/', $entry)) {
            return false;
        }

        if (preg_match('#^database/database\.(sqlite|sql)$#', $entry)) {
            return true;
        }

        foreach (array_keys(self::FILE_SETS) as $prefix) {
            if (str_starts_with($entry, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** Moves the current directory aside and the restored one in; returns the aside path. */
    private function swapDirectory(string $from, string $to, bool $private): string
    {
        $aside = $to.'.restore-'.Str::random(6);
        File::ensureDirectoryExists($to);

        if ($private) {
            // Keep backups and other excluded paths in place.
            File::ensureDirectoryExists($aside);
            foreach (File::directories($to) as $dir) {
                if (! $this->excluded(basename($dir))) {
                    File::moveDirectory($dir, $aside.'/'.basename($dir));
                }
            }
            foreach (File::files($to, true) as $file) {
                File::move($file->getPathname(), $aside.'/'.$file->getFilename());
            }
            if (is_dir($from)) {
                foreach (File::directories($from) as $dir) {
                    File::moveDirectory($dir, $to.'/'.basename($dir));
                }
                foreach (File::files($from, true) as $file) {
                    File::move($file->getPathname(), $to.'/'.$file->getFilename());
                }
            }

            return $aside;
        }

        if (! rename($to, $aside)) {
            throw new RuntimeException('Cannot move the current files aside.');
        }
        is_dir($from) ? File::moveDirectory($from, $to) : File::ensureDirectoryExists($to);

        return $aside;
    }

    private function unswapDirectory(string $to, string $aside, bool $private): void
    {
        if (! is_dir($aside)) {
            return;
        }

        if ($private) {
            foreach (File::directories($to) as $dir) {
                if (! $this->excluded(basename($dir))) {
                    File::deleteDirectory($dir);
                }
            }
            foreach (File::files($to, true) as $file) {
                @unlink($file->getPathname());
            }
            File::copyDirectory($aside, $to);
            File::deleteDirectory($aside);

            return;
        }

        File::deleteDirectory($to);
        rename($aside, $to);
    }

    private function prune(): void
    {
        $keep = max(1, (int) config('ecomodular.backup.keep', 7));

        foreach (array_slice($this->all(), $keep) as $old) {
            @unlink($old['path']);
        }
    }
}
