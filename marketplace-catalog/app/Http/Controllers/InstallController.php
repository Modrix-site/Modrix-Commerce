<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Marketplace\Models\User;
use Marketplace\Services\Installer;
use Marketplace\Services\InstallerException;

/** /install: step 1 MySQL/MariaDB, step 2 the first administrator. */
class InstallController
{
    public function __construct(private readonly Installer $installer) {}

    public function show(Request $request): View
    {
        $databaseReady = (bool) $request->session()->get('install.database');

        return view('install.show', [
            'step' => $databaseReady ? 'admin' : 'database',
            'hasAdmin' => $databaseReady && User::query()->where('is_admin', true)->exists(),
            'requirements' => [
                'PHP 8.3 or newer' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'pdo_mysql extension' => extension_loaded('pdo_mysql'),
                'zip extension' => extension_loaded('zip'),
                '.env is writable' => is_writable(app()->environmentFilePath()),
                'storage/ is writable' => is_writable(storage_path()),
            ],
        ]);
    }

    public function database(Request $request): RedirectResponse
    {
        $db = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_$-]+$/'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->installer->setUpDatabase($db);
        } catch (InstallerException $e) {
            return back()->withInput($request->except('password'))->withErrors(['host' => $e->getMessage()]);
        }

        $request->session()->put('install.database', true);

        return redirect('/install');
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('install.database')) {
            return redirect('/install');
        }

        if (! User::query()->where('is_admin', true)->exists()) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:191', 'unique:users,email'],
                'password' => ['required', 'confirmed:password_confirmation', Password::min(12)],
            ]);

            $admin = User::query()->create($data);
            $admin->forceFill(['is_admin' => true])->save();
        }

        $this->installer->markInstalled();
        $request->session()->forget('install.database');

        return view('install.done');
    }
}
