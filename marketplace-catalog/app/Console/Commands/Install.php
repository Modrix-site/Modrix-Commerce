<?php

namespace Marketplace\Console\Commands;

use Database\Seeders\CategorySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Marketplace\Models\User;
use Marketplace\Services\SigningKey;

/**
 * First-time setup: database tables, starting categories, the signing key
 * and the first administrator. Refused once an administrator exists.
 */
class Install extends Command
{
    protected $signature = 'marketplace:install {--name=} {--email=} {--password=}';

    protected $description = 'Create the database tables, the signing key and the first administrator';

    public function handle(SigningKey $key): int
    {
        $this->call('migrate', ['--force' => true]);

        if (User::query()->where('is_admin', true)->exists()) {
            $this->error('An administrator already exists. Sign in at /admin/login.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Administrator name'),
            'email' => $this->option('email') ?: $this->ask('Administrator email'),
            'password' => $this->option('password') ?: $this->secret('Password (at least 12 characters)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => CategorySeeder::class, '--force' => true]);

        $admin = User::query()->create($data);
        $admin->forceFill(['is_admin' => true])->save();

        $this->info('Administrator created. Sign in at '.url('/admin/login').'.');
        $this->line('Public signing key for stores (ECOMODULAR_MARKETPLACE_TRUSTED_KEYS):');
        $this->line($key->publicKey());

        return self::SUCCESS;
    }
}
