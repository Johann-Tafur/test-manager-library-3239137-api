<?php

namespace App\Domain\ValueObjects;

use App\Domain\DomainError;

/** CA-03.3 · Total de ejemplares mayor o igual a 1. */
final class Quantity
{
    public function __construct(public readonly int $value)
    {
        if ($value < 1) {
            throw new DomainError('VALIDATION_ERROR', 'El total de ejemplares debe ser al menos 1.', [
                ['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1'],
            ]);
        }
    }
}
