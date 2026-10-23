<?php

namespace App\Domain;

/** CA-08.1 · Estado calculado del préstamo. No tiene columna (artículo VII). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
