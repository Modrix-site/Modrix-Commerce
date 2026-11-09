<?php

namespace App\Http\Requests\Install;

use App\Core\Installation\InstallEnvironment;
use App\Core\Installation\InstallMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The database step only exists for manual installs: 404 before validation.
        abort_unless(app(InstallEnvironment::class)->mode() === InstallMode::Manual, 404);

        // Authorization is then the setup token, verified in the controller.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'setup_token' => ['required', 'string', 'max:128'],
        ];
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public function connection(): array
    {
        return [
            'host' => $this->string('host')->trim()->toString(),
            'port' => $this->integer('port'),
            'database' => $this->string('database')->trim()->toString(),
            'username' => $this->string('username')->trim()->toString(),
            'password' => (string) $this->input('db_password', ''),
        ];
    }
}
