<?php

namespace App\Console\Commands;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\Scaffolding\ModuleScaffolder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Signature('ecomodular:make:module {name : vendor/package} {--display-name= : Name shown to merchants} {--author= : Author or company}')]
#[Description('Create a new module package that validates, installs and enables unchanged')]
class MakeModuleCommand extends Command
{
    public function handle(ModuleScaffolder $scaffolder, ModuleRepository $modules): int
    {
        $name = (string) $this->argument('name');
        $directory = rtrim((string) config('ecomodular.modules.path'), '/\\').'/'.$name;
        $display = (string) ($this->option('display-name') ?: Str::headline(Str::after($name, '/')));

        try {
            $files = $scaffolder->create($name, $directory, $display, (string) ($this->option('author') ?: Str::headline(Str::before($name, '/'))));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->line("  created {$name}/{$file}");
        }

        $modules->sync();
        $extension = $modules->find($name);

        if ($extension === null || $extension->state === ModuleState::Invalid) {
            $this->error('The generated module did not validate: '.implode(' ', (array) $extension?->errors));

            return self::FAILURE;
        }

        $this->info("Module {$name} created and discovered.");
        $this->line('Next: php artisan ecomodular:modules install '.$name.' && php artisan ecomodular:modules enable '.$name.' --trust, then npm run build.');

        return self::SUCCESS;
    }
}
