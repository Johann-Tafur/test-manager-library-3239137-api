<?php

namespace App\Domain\ValueObjects;

use App\Domain\DomainError;

/** CA-10.2 · Fin anterior al inicio: 422 INVALID_DATE_RANGE. */
final class DateRange
{
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
    ) {
        if ($to < $from) {
            throw new DomainError('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.', [
                ['field' => 'to', 'issue' => 'anterior a from'],
            ]);
        }
    }
}
