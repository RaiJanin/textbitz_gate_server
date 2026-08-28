<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhilippineMobileNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match('/^\+639\d{9}$/', $value)) {
            $fail('The :attribute must be a valid PH mobile number in the format +639XXXXXXXXX.');
        }
    }
}
