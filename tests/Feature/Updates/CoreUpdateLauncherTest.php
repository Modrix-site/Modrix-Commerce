<?php

namespace Tests\Feature\Updates;

use App\Core\Updates\CoreUpdateLauncher;
use Tests\TestCase;

/** The detached launch command line and the PHP CLI it runs. */
class CoreUpdateLauncherTest extends TestCase
{
    public function test_windows_command_uses_start_with_an_empty_title(): void
    {
        $argv = (new CoreUpdateLauncher)->command('C:\\php 8\\php.exe', 'C:\\shop\\artisan', ['ecomodular:core-update', '42'], 'Windows');

        $this->assertSame(['cmd', '/c', 'start', '', '/B', 'C:\\php 8\\php.exe', 'C:\\shop\\artisan', 'ecomodular:core-update', '42'], $argv);
    }

    public function test_posix_command_backgrounds_a_quoted_command_in_its_own_session(): void
    {
        $argv = (new CoreUpdateLauncher)->command('/opt/php 8/bin/php', "/var/www/it's/artisan", ['ecomodular:core-update', '42'], 'Linux');

        $this->assertSame('sh', $argv[0]);
        $this->assertSame('-c', $argv[1]);
        $this->assertCount(3, $argv);

        $quoted = "'/opt/php 8/bin/php' '/var/www/it'\\''s/artisan' 'ecomodular:core-update' '42'";
        $line = "nohup {$quoted} < /dev/null > /dev/null 2>&1 &";
        $this->assertSame("if command -v setsid > /dev/null 2>&1; then setsid {$line} else {$line} fi", $argv[2]);
    }

    public function test_configured_php_binary_wins(): void
    {
        $this->assertSame('/usr/bin/php8.3', CoreUpdateLauncher::phpBinary('/usr/bin/php8.3', 'fpm-fcgi', '/usr/sbin/php-fpm8.3'));
    }

    public function test_cli_uses_its_own_binary(): void
    {
        $this->assertSame('/usr/bin/php', CoreUpdateLauncher::phpBinary(null, 'cli', '/usr/bin/php'));
        $this->assertSame('/usr/bin/php', CoreUpdateLauncher::phpBinary('', 'cli', '/usr/bin/php'));
    }

    public function test_fpm_and_cgi_binaries_are_replaced_by_a_cli(): void
    {
        foreach ([['fpm-fcgi', '/usr/sbin/php-fpm8.3'], ['cgi-fcgi', 'C:\\php\\php-cgi.exe'], ['cli', '/usr/sbin/php-fpm']] as [$sapi, $binary]) {
            $php = CoreUpdateLauncher::phpBinary(null, $sapi, $binary);

            $this->assertNotSame($binary, $php);
            $this->assertDoesNotMatchRegularExpression('/fpm|cgi/i', basename($php));
        }
    }

    public function test_default_config_leaves_the_binary_to_the_resolver(): void
    {
        $this->assertNull(config('ecomodular.updates.php'));
        $this->assertSame(PHP_BINARY, CoreUpdateLauncher::phpBinary());
    }
}
