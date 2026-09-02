<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PasswordComplexity implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        return preg_match('/[A-Z]/', $value) && // At least one uppercase
               preg_match('/[a-z]/', $value) && // At least one lowercase
               preg_match('/[0-9]/', $value) && // At least one number
               preg_match('/[!@#$%^&*()\-_=+\[\]{}|;:,.<>?]/', $value); // At least one special char
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    }
}
