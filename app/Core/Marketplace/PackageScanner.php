<?php

namespace App\Core\Marketplace;

use Illuminate\Support\Facades\File;
use PhpToken;
use Throwable;

/**
 * Static scan of an extension package for patterns a reviewer must look at:
 * code execution, shell access, obfuscation, outbound network calls, raw SQL,
 * environment and secret access, and files that should never be in a
 * package. It informs the human review; it does not prove a package is safe
 * (and a clean scan is not a security guarantee).
 *
 * Severity: critical (almost never legitimate), high (needs a justification),
 * medium (common but sensitive), info.
 */
final class PackageScanner
{
    public const CRITICAL = 'critical';

    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const INFO = 'info';

    /** Function name => [severity, reason]. */
    private const FUNCTIONS = [
        'eval' => [self::CRITICAL, 'Runs a string as PHP code'],
        'assert' => [self::HIGH, 'Can evaluate code in old configurations'],
        'create_function' => [self::CRITICAL, 'Runs a string as PHP code'],
        'exec' => [self::CRITICAL, 'Runs a shell command'],
        'shell_exec' => [self::CRITICAL, 'Runs a shell command'],
        'system' => [self::CRITICAL, 'Runs a shell command'],
        'passthru' => [self::CRITICAL, 'Runs a shell command'],
        'proc_open' => [self::CRITICAL, 'Starts a process'],
        'popen' => [self::CRITICAL, 'Starts a process'],
        'pcntl_exec' => [self::CRITICAL, 'Replaces the PHP process'],
        'dl' => [self::CRITICAL, 'Loads a PHP extension'],
        'base64_decode' => [self::MEDIUM, 'Decodes data; check it is not hiding code'],
        'gzinflate' => [self::HIGH, 'Decompresses data; a common obfuscation step'],
        'gzuncompress' => [self::HIGH, 'Decompresses data; a common obfuscation step'],
        'str_rot13' => [self::HIGH, 'A common obfuscation step'],
        'unserialize' => [self::HIGH, 'Object injection if the input is not trusted'],
        'call_user_func' => [self::MEDIUM, 'Calls a function chosen at runtime'],
        'call_user_func_array' => [self::MEDIUM, 'Calls a function chosen at runtime'],
        'curl_exec' => [self::MEDIUM, 'Outbound network request'],
        'fsockopen' => [self::HIGH, 'Opens a raw network connection'],
        'stream_socket_client' => [self::HIGH, 'Opens a raw network connection'],
        'file_get_contents' => [self::INFO, 'Reads a file or URL'],
        'file_put_contents' => [self::MEDIUM, 'Writes a file'],
        'fopen' => [self::INFO, 'Opens a file or URL'],
        'unlink' => [self::MEDIUM, 'Deletes a file'],
        'chmod' => [self::MEDIUM, 'Changes file permissions'],
        'putenv' => [self::HIGH, 'Changes the process environment'],
        'ini_set' => [self::MEDIUM, 'Changes PHP configuration at runtime'],
        'getenv' => [self::MEDIUM, 'Reads environment variables (may include secrets)'],
        'env' => [self::MEDIUM, 'Reads environment variables; use module settings instead'],
        'phpinfo' => [self::HIGH, 'Reveals server configuration'],
        'mail' => [self::MEDIUM, 'Sends mail outside the application mailer'],
        'header' => [self::INFO, 'Sends a raw HTTP header'],
        'extract' => [self::MEDIUM, 'Creates variables from input'],
        'parse_str' => [self::INFO, 'Creates variables from a string'],
        'move_uploaded_file' => [self::MEDIUM, 'Stores an upload'],
    ];

    /** Static calls Class::method => [severity, reason]. */
    private const STATIC_CALLS = [
        'db::unprepared' => [self::HIGH, 'Runs raw SQL without bindings'],
        'db::statement' => [self::MEDIUM, 'Runs raw SQL'],
        'db::raw' => [self::MEDIUM, 'Raw SQL fragment; check that input is bound'],
        'artisan::call' => [self::HIGH, 'Runs console commands'],
        'http::get' => [self::MEDIUM, 'Outbound network request'],
        'http::post' => [self::MEDIUM, 'Outbound network request'],
        'process::run' => [self::CRITICAL, 'Starts a process'],
        'storage::disk' => [self::INFO, 'Uses a storage disk'],
        'crypt::decrypt' => [self::MEDIUM, 'Decrypts application data'],
    ];

    /** File extensions that never belong in a package. */
    private const FORBIDDEN_EXTENSIONS = ['phar', 'so', 'dll', 'exe', 'sh', 'bat', 'cmd', 'ps1', 'bin', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht'];

    private const FORBIDDEN_NAMES = ['.htaccess', '.user.ini', 'php.ini', '.env'];

    /**
     * @return list<array{severity: string, rule: string, file: string, line: int, message: string}>
     */
    public function scan(string $directory): array
    {
        $findings = [];

        foreach (File::allFiles($directory, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $extension = strtolower($file->getExtension());
            $basename = strtolower($file->getFilename());

            if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true) || in_array($basename, self::FORBIDDEN_NAMES, true)) {
                $findings[] = $this->finding(self::CRITICAL, 'forbidden-file', $relative, 0, "This file type ({$basename}) must not be in a package.");

                continue;
            }

            if ($extension === 'php') {
                array_push($findings, ...$this->scanPhp((string) file_get_contents($file->getPathname()), $relative));
            } elseif (in_array($extension, ['js', 'ts', 'tsx', 'jsx'], true)) {
                array_push($findings, ...$this->scanScript((string) file_get_contents($file->getPathname()), $relative));
            }
        }

        usort($findings, fn (array $a, array $b) => [self::rank($a['severity']), $a['file'], $a['line']] <=> [self::rank($b['severity']), $b['file'], $b['line']]);

        return $findings;
    }

    /**
     * @param  list<array{severity: string}>  $findings
     * @return array<string, int>
     */
    public static function summary(array $findings): array
    {
        $summary = [self::CRITICAL => 0, self::HIGH => 0, self::MEDIUM => 0, self::INFO => 0];
        foreach ($findings as $finding) {
            $summary[$finding['severity']]++;
        }

        return $summary;
    }

    private static function rank(string $severity): int
    {
        return match ($severity) {
            self::CRITICAL => 0,
            self::HIGH => 1,
            self::MEDIUM => 2,
            default => 3,
        };
    }

    /**
     * @return list<array{severity: string, rule: string, file: string, line: int, message: string}>
     */
    private function scanPhp(string $source, string $file): array
    {
        try {
            $tokens = PhpToken::tokenize($source, TOKEN_PARSE);
        } catch (Throwable) {
            return [$this->finding(self::HIGH, 'parse-error', $file, 0, 'The file does not parse as PHP.')];
        }

        $findings = [];
        $significant = array_values(array_filter($tokens, fn (PhpToken $t) => ! $t->isIgnorable()));
        $count = count($significant);

        for ($i = 0; $i < $count; $i++) {
            $token = $significant[$i];
            $previous = $significant[$i - 1] ?? null;
            $next = $significant[$i + 1] ?? null;

            // Backtick shell execution.
            if ($token->text === '`') {
                $findings[] = $this->finding(self::CRITICAL, 'shell', $file, $token->line, 'Backtick operator runs a shell command.');
                while (++$i < $count && $significant[$i]->text !== '`');

                continue;
            }

            if ($token->is(T_EVAL)) {
                $findings[] = $this->finding(self::CRITICAL, 'eval', $file, $token->line, 'Runs a string as PHP code (eval).');

                continue;
            }

            // include/require with a computed path.
            if ($token->is([T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE]) && $next !== null && ! $next->is([T_CONSTANT_ENCAPSED_STRING, T_DIR])) {
                $findings[] = $this->finding(self::HIGH, 'dynamic-include', $file, $token->line, 'Includes a file whose path is computed at runtime.');

                continue;
            }

            // Variable functions: $fn(...)
            if ($token->is(T_VARIABLE) && $next?->text === '(' && ! ($previous !== null && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_FN]))) {
                $findings[] = $this->finding(self::MEDIUM, 'variable-function', $file, $token->line, "Calls a function stored in {$token->text}.");

                continue;
            }

            if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || $next?->text !== '(') {
                // Long base64-looking literals often hide payloads.
                if ($token->is(T_CONSTANT_ENCAPSED_STRING) && strlen($token->text) > 400 && preg_match('/^[\'"][A-Za-z0-9+\/=\s]+[\'"]$/', $token->text)) {
                    $findings[] = $this->finding(self::HIGH, 'encoded-blob', $file, $token->line, 'Long encoded string literal; check what it contains.');
                }

                continue;
            }

            $name = strtolower(ltrim($token->text, '\\'));
            $isMethod = $previous !== null && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION, T_NEW]);

            if ($previous !== null && $previous->is(T_DOUBLE_COLON)) {
                $class = strtolower(ltrim((string) ($significant[$i - 2]->text ?? ''), '\\'));
                $class = substr($class, (int) strrpos('\\'.$class, '\\'));
                $key = $class.'::'.$name;

                if (isset(self::STATIC_CALLS[$key])) {
                    [$severity, $reason] = self::STATIC_CALLS[$key];
                    $findings[] = $this->finding($severity, 'static-call', $file, $token->line, "{$reason} ({$token->text}).");
                }

                continue;
            }

            if (! $isMethod && isset(self::FUNCTIONS[$name])) {
                [$severity, $reason] = self::FUNCTIONS[$name];
                $findings[] = $this->finding($severity, 'function', $file, $token->line, "{$reason} ({$name}).");
            }
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, rule: string, file: string, line: int, message: string}>
     */
    private function scanScript(string $source, string $file): array
    {
        $findings = [];
        $rules = [
            '/\beval\s*\(/' => [self::CRITICAL, 'eval() runs a string as code.'],
            '/\bnew\s+Function\s*\(/' => [self::CRITICAL, 'new Function() runs a string as code.'],
            '/dangerouslySetInnerHTML/' => [self::HIGH, 'Renders raw HTML; check that the content is sanitised.'],
            '/\bdocument\.write\s*\(/' => [self::HIGH, 'document.write injects markup.'],
            '/\bfetch\s*\(\s*[\'"`]https?:\/\//' => [self::MEDIUM, 'Calls an external URL from the browser (blocked by the default CSP).'],
            '/<script\b/i' => [self::HIGH, 'Inline or injected script tag.'],
        ];

        foreach (preg_split('/\R/', $source) ?: [] as $index => $line) {
            foreach ($rules as $pattern => [$severity, $message]) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->finding($severity, 'script', $file, $index + 1, $message);
                }
            }
        }

        return $findings;
    }

    /**
     * @return array{severity: string, rule: string, file: string, line: int, message: string}
     */
    private function finding(string $severity, string $rule, string $file, int $line, string $message): array
    {
        return compact('severity', 'rule', 'file', 'line', 'message');
    }
}
