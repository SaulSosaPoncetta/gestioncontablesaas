<?php

namespace App\Rules;

use App\Models\Empresa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cuit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Empresa::cuitValido((string) $value)) {
            $fail('El CUIT ingresado no es válido (verifique el dígito verificador).');
        }
    }
}
