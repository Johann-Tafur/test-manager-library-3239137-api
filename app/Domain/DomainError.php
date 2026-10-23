<?php

namespace App\Domain;

/**
 * Error de negocio. Lleva el `code` del contrato (api-contract.md §2).
 * Solo el adaptador HTTP lo traduce a estado HTTP y cuerpo de error (architecture.md §5).
 */
final class DomainError extends \DomainException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
