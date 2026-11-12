<?php

namespace App\Core\Authorization\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Builds the validation error raised when a role/user change would break an
 * RBAC invariant (escalation, self-modification, last-owner removal), so the
 * admin UI can show the reason next to the relevant field.
 */
final class AuthorizationRuleViolation
{
    public static function make(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}
