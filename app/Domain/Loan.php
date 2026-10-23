<?php

namespace App\Domain;

/**
 * Agregado Préstamo (architecture.md §3). Hecho inmutable: solo cambia returned_at de una línea.
 * Reglas: HU-06 (registrar) y HU-07 (devolver).
 */
final class Loan
{
    public const LOAN_DAYS = 14;              // RN-2
    public const MAX_ITEMS = 3;               // CA-06.1
    public const MAX_UNRETURNED_PER_MEMBER = 3; // CA-06.4

    /** @var list<LoanItem> */
    private array $items = [];

    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly \DateTimeImmutable $loanedAt,
    ) {}

    /**
     * Registra un préstamo (HU-06).
     *
     * @param list<array{book: Book, categoryName: string}> $lines  libro y nombre de su categoría en este instante
     * @param int  $memberUnreturned  libros que el socio tiene sin devolver ahora
     * @param bool $memberHasOverdue  si el socio tiene algún libro vencido
     */
    public static function lend(
        string $id,
        string $memberId,
        string $loanedByUserId,
        \DateTimeImmutable $now,
        array $lines,
        int $memberUnreturned,
        bool $memberHasOverdue,
    ): self {
        // CA-06.5 · Socio con algún vencido no pide más (409)
        if ($memberHasOverdue) {
            throw new DomainError('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y debe devolverlos antes de pedir otro.');
        }

        // CA-06.1 · De 1 a 3 líneas
        $count = count($lines);
        if ($count < 1 || $count > self::MAX_ITEMS) {
            throw new DomainError('VALIDATION_ERROR', 'Un préstamo lleva de 1 a 3 libros.', [
                ['field' => 'items', 'issue' => 'debe tener entre 1 y 3 elementos'],
            ]);
        }

        // CA-06.2 · Sin libro repetido (422). Se valida ANTES de descontar ejemplares.
        $bookIds = array_map(fn (array $line) => $line['book']->id, $lines);
        if (count(array_unique($bookIds)) !== $count) {
            throw new DomainError('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir el mismo libro en un préstamo.', [
                ['field' => 'items', 'issue' => 'libro repetido'],
            ]);
        }

        // CA-06.4 · Máximo 3 sin devolver por socio (422 con el conteo actual)
        if ($memberUnreturned + $count > self::MAX_UNRETURNED_PER_MEMBER) {
            throw new DomainError('MEMBER_LIMIT_EXCEEDED', 'El socio ya tiene '.$memberUnreturned.' libro(s) sin devolver; el máximo es 3.', [
                ['field' => 'items', 'issue' => 'sin devolver: '.$memberUnreturned],
            ]);
        }

        $loan = new self($id, $memberId, $loanedByUserId, $now);
        foreach ($lines as $line) {
            $loan->addItem($line['book'], $line['categoryName']);
        }

        return $loan;
    }

    /**
     * Descontar el ejemplar y crear la línea son UNA operación (architecture.md §3).
     * CA-06.6 · Vence a los 14 días y congela título, ISBN y categoría.
     */
    private function addItem(Book $book, string $categoryName): void
    {
        $book->withdraw();
        $this->items[] = new LoanItem(
            self::newId(),
            $book->id,
            $book->title(),
            $book->isbn()->value,
            $categoryName,
            $this->loanedAt->modify('+'.self::LOAN_DAYS.' days'),
        );
    }

    /** HU-07 · Se devuelve una línea a la vez; suma 1 al libro una sola vez. */
    public function returnItem(string $itemId, Book $book, \DateTimeImmutable $now): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                $item->markReturned($now); // lanza ITEM_ALREADY_RETURNED antes de tocar el libro
                $book->restock();

                return $item;
            }
        }
        throw new DomainError('NOT_FOUND', 'La línea de préstamo no existe.');
    }

    /** CA-07.4 y CA-08.1 · El estado se calcula: closed, overdue u open. */
    public function status(\DateTimeImmutable $now): LoanStatus
    {
        $pending = array_filter($this->items, fn (LoanItem $item) => ! $item->isReturned());
        if ($pending === []) {
            return LoanStatus::Closed;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** UUID v4 con la biblioteca estándar (el dominio no depende de nada, artículo I). */
    private static function newId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
