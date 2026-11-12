<?php

namespace App\Console\Commands;

use App\Core\Marketplace\PackageArchive;
use App\Core\Marketplace\PackageException;
use App\Core\Marketplace\PackageScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Builds the archive a marketplace upload expects and runs the same checks
 * (archive safety, manifest validation, name match) plus the security scan,
 * so developers see problems before submitting.
 */
#[Signature('ecomodular:package {name : vendor/package} {--type=module : module or theme} {--output= : Directory for the zip (default storage/app/private/packages)} {--strict : Fail on critical or high scan findings}')]
#[Description('Validate, scan and package a module or theme for the marketplace')]
class PackageCommand extends Command
{
    public function handle(PackageScanner $scanner): int
    {
        $name = (string) $this->argument('name');
        $type = (string) $this->option('type');

        if (! in_array($type, ['module', 'theme'], true) || ! preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $name)) {
            $this->error('Use a vendor/package name and --type=module or --type=theme.');

            return self::FAILURE;
        }

        $source = rtrim((string) config($type === 'module' ? 'ecomodular.modules.path' : 'ecomodular.themes.path'), '/\\').'/'.$name;
        if (! File::isDirectory($source)) {
            $this->error("{$source} does not exist.");

            return self::FAILURE;
        }

        $output = rtrim((string) ($this->option('output') ?: storage_path('app/private/packages')), '/\\');
        File::ensureDirectoryExists($output);
        $temporary = $output.'/'.str_replace('/', '-', $name).'-building.zip';
        $extracted = null;

        try {
            PackageArchive::create($source, $temporary);
            ['dir' => $extracted, 'manifest' => $manifest] = PackageArchive::extract($temporary, $type, $name);
        } catch (PackageException $e) {
            File::delete($temporary);
            $this->error('The package is not valid: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $findings = $scanner->scan($extracted);
        } finally {
            File::deleteDirectory($extracted);
        }

        $zip = $output.'/'.str_replace('/', '-', $name).'-'.$manifest->version.'.zip';
        File::move($temporary, $zip);

        $summary = PackageScanner::summary($findings);
        if ($findings !== []) {
            $this->table(['Severity', 'File', 'Line', 'Finding'], array_map(fn (array $f) => [$f['severity'], $f['file'], $f['line'] ?: '', $f['message']], $findings));
        }
        $this->line(sprintf('Scan: %d critical, %d high, %d medium, %d info.', $summary['critical'], $summary['high'], $summary['medium'], $summary['info']));
        $this->info("Package: {$zip} (".number_format((int) filesize($zip) / 1024, 1).' KB, SHA-256 '.hash_file('sha256', $zip).')');

        if ($this->option('strict') && ($summary['critical'] > 0 || $summary['high'] > 0)) {
            $this->error('Strict mode: resolve or justify the critical and high findings before submitting.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
