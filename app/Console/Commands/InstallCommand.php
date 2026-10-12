<?php

namespace App\Console\Commands;

use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallPlatform;
use App\Core\Installation\RequirementChecker;
use App\Core\Settings\Currencies;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('ecomodular:install
    {--store-name= : Store name}
    {--name= : Owner full name}
    {--email= : Owner email address}
    {--currency=USD : Default ISO 4217 currency}
    {--timezone= : Store timezone (defaults to APP_TIMEZONE)}')]
#[Description('Install EcoModular: verify requirements, create the owner account and lock the installer')]
class InstallCommand extends Command
{
    public function handle(InstallationState $state, RequirementChecker $requirements, InstallPlatform $install): int
    {
        if ($state->isInstalled()) {
            $this->components->error('EcoModular is already installed. The installer cannot be re-run.');

            return self::FAILURE;
        }

        $checks = $requirements->check();
        $this->table(['Requirement', 'Status', 'Detail'], array_map(
            fn ($c) => [$c['label'], $c['passed'] ? 'OK' : 'FAIL', $c['detail']],
            $checks,
        ));

        if (! RequirementChecker::allPassed($checks)) {
            $this->components->error('Resolve the failing requirements above (run "php artisan migrate" first), then try again.');

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();

        $data = [
            'store_name' => $this->option('store-name') ?: ($interactive ? text('Store name', required: true) : null),
            'name' => $this->option('name') ?: ($interactive ? text('Owner name', required: true) : null),
            'email' => $this->option('email') ?: ($interactive ? text('Owner email', required: true) : null),
            'default_currency' => strtoupper((string) ($this->option('currency') ?: ($interactive ? select('Default currency', Currencies::codes(), 'USD') : 'USD'))),
            'timezone' => $this->option('timezone') ?: config('app.timezone'),
            // Passwords are never accepted as CLI options: they would end up in shell history.
            'password' => $interactive ? password('Owner password', required: true) : (string) getenv('ECOMODULAR_OWNER_PASSWORD'),
        ];

        $validator = Validator::make($data, [
            'store_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'default_currency' => ['required', Rule::in(Currencies::codes())],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'password' => ['required', 'string', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            if (! $interactive) {
                $this->line('Non-interactive installs read the owner password from the ECOMODULAR_OWNER_PASSWORD environment variable.');
            }

            return self::FAILURE;
        }

        /** @var array{name: string, email: string, password: string, store_name: string, default_currency: string, timezone: string} $validated */
        $validated = $validator->validated();
        $owner = $install->handle($validated);

        $this->components->info("Installed. Owner account: {$owner->email}");
        $this->line('Next: review docs/installation/production.md for the post-install hardening checklist.');

        return self::SUCCESS;
    }
}
