<?php

namespace App\Core\Updates;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Starts `ecomodular:core-update {run}` in a detached PHP process so the
 * install outlives the web request. Bound in the container; tests swap it.
 *
 * The launch itself is a short synchronous call: a shell (`sh -c '… &'` or
 * `cmd /c start`) that starts the install in the background and returns at
 * once. Nothing keeps a handle on the started process, so no destructor
 * can stop it.
 */
class CoreUpdateLauncher
{
    public function launch(CoreUpdateRun $run): void
    {
        $root = (string) config('ecomodular.updates.root', base_path());

        $result = Process::path($root)->timeout(30)->quietly()
            ->run($this->command(self::phpBinary(), $root.DIRECTORY_SEPARATOR.'artisan', ['ecomodular:core-update', (string) $run->getKey()]));

        if ($result->failed()) {
            throw new CoreUpdateError('The update process could not be started (exit '.($result->exitCode() ?? '?').').');
        }
    }

    /**
     * The argv that runs `php <script> <args>` detached from this process.
     *
     * @param  list<string>  $args
     * @return list<string>
     */
    public function command(string $php, string $script, array $args = [], string $os = PHP_OS_FAMILY): array
    {
        if ($os === 'Windows') {
            // `start` returns at once; the empty title is quoted to "" by the process component.
            return ['cmd', '/c', 'start', '', '/B', $php, $script, ...$args];
        }

        // POSIX single quotes, not escapeshellarg(): its quoting follows the host OS.
        $line = implode(' ', array_map(fn (string $part): string => "'".str_replace("'", "'\''", $part)."'", [$php, $script, ...$args]));

        $line = 'nohup '.$line.' < /dev/null > /dev/null 2>&1 &';

        // setsid puts the install in its own session, so stopping the web worker does not stop it.
        return ['sh', '-c', 'if command -v setsid > /dev/null 2>&1; then setsid '.$line.' else '.$line.' fi'];
    }

    /**
     * The PHP CLI binary for the install process. `ecomodular.updates.php`
     * wins when set; under PHP-FPM or CGI, PHP_BINARY is not a CLI, so the
     * CLI is looked up instead.
     */
    public static function phpBinary(mixed $configured = null, string $sapi = PHP_SAPI, string $binary = PHP_BINARY): string
    {
        $configured = func_num_args() === 0 ? config('ecomodular.updates.php') : $configured;

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        $isCli = fn (string $path): bool => $path !== '' && ! preg_match('/fpm|cgi/i', basename($path));

        if ($sapi === 'cli' && $isCli($binary)) {
            return $binary;
        }

        $found = (new PhpExecutableFinder)->find(false);
        if (is_string($found) && $isCli($found)) {
            return $found;
        }

        $candidate = PHP_BINDIR.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');

        return is_file($candidate) ? $candidate : 'php';
    }
}
