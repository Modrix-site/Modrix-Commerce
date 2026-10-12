<?php

namespace App\Http\Requests\Install;

use App\Concerns\PasswordValidationRules;
use App\Core\Settings\Currencies;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        // Authorization is the setup token, verified in the controller so a wrong
        // token produces a field error rather than a bare 403.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'setup_token' => ['required', 'string', 'max:128'],
            'store_name' => ['required', 'string', 'max:120'],
            'default_currency' => ['required', 'string', Rule::in(Currencies::codes())],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
        ];
    }
}
