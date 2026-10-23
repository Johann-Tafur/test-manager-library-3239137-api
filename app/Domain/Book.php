<?php

namespace App\Domain;

use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

/**
 * Agregado Libro (architecture.md §3).
 * Invariante RN-1: 0 <= disponibles <= total. Baja lógica con deleted_at (ADR-003).
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        private Isbn $isbn,
        private string $categoryId,
        private int $totalCopies,
        private int $availableCopies,
        private ?\DateTimeImmutable $deletedAt,
    ) {}

    /** CA-03.1 · Los disponibles arrancan iguales al total. */
    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $total): self
    {
        return new self($id, self::requireText($title, 'title'), self::requireText($author, 'author'), $isbn, $categoryId, $total->value, $total->value, null);
    }

    /** CA-06.3 · Cada línea de préstamo descuenta 1 ejemplar. Sin ejemplares: BOOK_NOT_AVAILABLE. */
    public function withdraw(): void
    {
        if ($this->availableCopies === 0) {
            throw new DomainError('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles de «'.$this->title.'».', [
                ['field' => 'book_id', 'issue' => 'sin ejemplares'],
            ]);
        }
        $this->availableCopies--;
    }

    /** CA-07.1 y CA-07.3 · Devolver suma 1, y nunca por encima del total. */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new \LogicException('Los disponibles no pueden superar el total.');
        }
        $this->availableCopies++;
    }

    /** CA-03.3 · Cambiar el total ajusta los disponibles en la misma diferencia; nunca quedan < 0. */
    public function changeTotal(Quantity $newTotal): void
    {
        $newAvailable = $this->availableCopies + ($newTotal->value - $this->totalCopies);
        if ($newAvailable < 0) {
            throw new DomainError('VALIDATION_ERROR', 'El nuevo total es menor que los ejemplares prestados.', [
                ['field' => 'total_copies', 'issue' => 'dejaría disponibles negativos'],
            ]);
        }
        $this->totalCopies = $newTotal->value;
        $this->availableCopies = $newAvailable;
    }

    /** CA-03.5 · Baja lógica. Con préstamos abiertos no se da de baja: BOOK_HAS_OPEN_LOANS. */
    public function delete(\DateTimeImmutable $now, bool $hasOpenLoans): void
    {
        if ($hasOpenLoans) {
            throw new DomainError('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos sin devolver y no se puede dar de baja.');
        }
        $this->deletedAt = $now;
    }

    public function title(): string { return $this->title; }
    public function isbn(): Isbn { return $this->isbn; }
    public function totalCopies(): int { return $this->totalCopies; }
    public function availableCopies(): int { return $this->availableCopies; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }

    private static function requireText(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new DomainError('VALIDATION_ERROR', 'El campo '.$field.' es obligatorio.', [
                ['field' => $field, 'issue' => 'vacío'],
            ]);
        }

        return $value;
    }
}
