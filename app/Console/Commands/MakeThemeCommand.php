<?php

namespace App\Console\Commands;

use App\Core\Extensions\Scaffolding\ThemeScaffolder;
use App\Core\Themes\ThemeRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Signature('ecomodular:make:theme {name : vendor/theme} {--from=ecomodular/essence : Theme to start from} {--display-name= : Name shown to merchants} {--author= : Author or company}')]
#[Description('Create a new theme package from an existing theme')]
class MakeThemeCommand extends Command
{
    public function handle(ThemeScaffolder $scaffolder, ThemeRepository $themes): int
    {
        $name = (string) $this->argument('name');
        $root = rtrim((string) config('ecomodular.themes.path'), '/\\');
        $from = (string) $this->option('from');

        if (! preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $from) || ! File::isFile("{$root}/{$from}/theme.json")) {
            $this->error("The base theme {$from} was not found in the themes directory.");

            return self::FAILURE;
        }

        try {
            $files = $scaffolder->create(
                $name,
                "{$root}/{$name}",
                "{$root}/{$from}",
                (string) ($this->option('display-name') ?: Str::headline(Str::after($name, '/'))),
                (string) ($this->option('author') ?: Str::headline(Str::before($name, '/'))),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        [, $errors] = $themes->validate($name, "{$root}/{$name}");
        if ($errors !== []) {
            $this->error('The generated theme did not validate: '.implode(' ', $errors));

            return self::FAILURE;
        }

        $themes->sync();
        $this->info("Theme {$name} created from {$from} (".count($files).' files).');
        $this->line('Next: edit theme.json (design, layout, sections), then preview it under Admin → Appearance.');

        return self::SUCCESS;
    }
}
