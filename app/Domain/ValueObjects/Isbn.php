<?php

namespace App\Domain\ValueObjects;

use App\Domain\DomainError;

/** CA-03.2 · El ISBN tiene 13 dígitos. Objeto de valor: sin tabla propia (D-07). */
final class Isbn
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (! preg_match('/^\d{13}$/', $value)) {
            throw new DomainError('VALIDATION_ERROR', 'El ISBN debe tener exactamente 13 dígitos.', [
                ['field' => 'isbn', 'issue' => 'debe tener 13 dígitos'],
            ]);
        }
        $this->value = $value;
    }
}
