<?php

namespace Src\Shared\Interface\Http\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The one rule for choosing a password (registration, invitation acceptance, password reset, the admin console command).
 * The breach check sends only the first 5 characters of the SHA-1 hash to Have I Been Pwned; when the service cannot be reached
 * the password is accepted. Sign-in does not use it: an existing password is never re-judged there.
 */
final class NewPassword
{
    public const int MIN_LENGTH = 8;

    public const int MAX_LENGTH = 255;

    /** @return list<string|Rule> */
    public static function rules(): array
    {
        return ['required', 'string', Password::min(self::MIN_LENGTH)->uncompromised(), 'max:'.self::MAX_LENGTH];
    }
}
