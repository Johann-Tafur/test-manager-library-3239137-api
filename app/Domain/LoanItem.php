<?php

namespace App\Domain;

/**
 * Línea de préstamo. Título, ISBN y categoría quedan CONGELADOS en el instante del préstamo (CA-06.6, ADR-004).
 * Lo único que cambia después es returned_at (CA-06.7).
 */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly \DateTimeImmutable $dueAt,
        private ?\DateTimeImmutable $returnedAt = null,
    ) {}

    /** CA-07.2 · Devolver una línea ya devuelta: ITEM_ALREADY_RETURNED. Nunca suma dos veces. */
    public function markReturned(\DateTimeImmutable $now): void
    {
        if ($this->returnedAt !== null) {
            throw new DomainError('ITEM_ALREADY_RETURNED', 'Este libro ya fue devuelto.');
        }
        $this->returnedAt = $now;
    }

    public function returnedAt(): ?\DateTimeImmutable { return $this->returnedAt; }

    public function isReturned(): bool { return $this->returnedAt !== null; }

    /** CA-09.1 · Vencida: sin devolver y con due_at anterior a ahora. */
    public function isOverdue(\DateTimeImmutable $now): bool
    {
        return $this->returnedAt === null && $this->dueAt < $now;
    }

    /** CA-09.1 · Días de retraso. Se calcula, no se guarda (artículo VII). */
    public function daysLate(\DateTimeImmutable $now): int
    {
        return $this->isOverdue($now) ? (int) $this->dueAt->diff($now)->days : 0;
    }
}
