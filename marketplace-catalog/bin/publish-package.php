<?php

/**
 * Kept for the module READMEs: `php marketplace-catalog/bin/publish-package.php vendor/package`
 * runs `php artisan marketplace:publish vendor/package`.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$command = [PHP_BINARY, dirname(__DIR__).'/artisan', 'marketplace:publish', ...array_slice($argv, 1)];
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);

exit(is_resource($process) ? proc_close($process) : 1);
