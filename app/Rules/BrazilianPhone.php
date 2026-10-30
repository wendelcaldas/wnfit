<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrazilianPhone implements ValidationRule
{
    public static function digits(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        return preg_match('/^55\d{10,11}$/', $digits) ? substr($digits, 2) : $digits;
    }

    public static function valid(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 30
            && preg_match('/\A\+?[0-9\s().-]+\z/', $value)
            && preg_match('/\A[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})\z/', self::digits($value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valid($value)) {
            $fail('Informe um telefone válido com DDD.');
        }
    }
}
