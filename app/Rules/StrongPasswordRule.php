<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPasswordRule implements ValidationRule
{
    /**
     * Validate the given password value against strong password rules.
     *
     * The password must:
     * - Be a string
     * - Be at least 8 characters long
     * - Include at least one uppercase letter
     * - Include at least one lowercase letter
     * - Include at least one digit
     * - Include at least one special character
     *
     * @param  string  $attribute  The name of the field under validation.
     * @param  mixed   $value      The actual value being validated.
     * @param  \Closure  $fail     Callback to trigger validation failure.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // If not a string, fail immediately
        if (!is_string($value)) {
            $fail("The :attribute must be a string.");
            return;
        }

        // Accumulate all specific errors into an array
        $errors = [];

        // Check password length
        if (strlen($value) < 8) {
            $errors[] = "The :attribute must be at least 8 characters long.";
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = "The :attribute must include at least one uppercase letter.";
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = "The :attribute must include at least one lowercase letter.";
        }

        // Check for at least one digit
        if (!preg_match('/\d/', $value)) {
            $errors[] = "The :attribute must include at least one number.";
        }

        // Check for at least one special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $errors[] = "The :attribute must include at least one special character.";
        }

        // Trigger validation failure for each specific rule broken
        foreach ($errors as $error) {
            $fail($error);
        }
    }
}
