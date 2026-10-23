<?php

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * Evidencia de T-05: open, overdue y closed; 14 días; máximo 3; congelado.
 * El "reloj falso" es una fecha fija que se pasa al dominio: no hay que esperar 14 días (D-05).
 */
class LoanTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-14T14:15:00Z');
    }

    private function book(string $id, int $copies = 2, string $isbn = '9780307474728'): Book
    {
        return Book::create($id, 'Libro '.$id, 'Autor', new Isbn($isbn), 'cat-1', new Quantity($copies));
    }

    /** @param list<Book> $books */
    private function lend(array $books, int $unreturned = 0, bool $overdue = false): Loan
    {
        $lines = array_map(fn (Book $b) => ['book' => $b, 'categoryName' => 'Novela'], $books);

        return Loan::lend('loan-1', 'member-1', 'user-1', $this->now, $lines, $unreturned, $overdue);
    }

    private function assertDomainError(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Debió lanzar '.$code);
        } catch (DomainError $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }

    public function test_vence_a_los_14_dias(): void
    {
        $loan = $this->lend([$this->book('b1')]);
        $this->assertEquals(new \DateTimeImmutable('2026-10-28T14:15:00Z'), $loan->items()[0]->dueAt);
    }

    public function test_cada_linea_descuenta_un_ejemplar(): void
    {
        $book = $this->book('b1', 2);
        $this->lend([$book]);
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_la_linea_congela_titulo_isbn_y_categoria(): void
    {
        $item = $this->lend([$this->book('b1')])->items()[0];
        $this->assertSame('Libro b1', $item->bookTitle);
        $this->assertSame('9780307474728', $item->isbn);
        $this->assertSame('Novela', $item->categoryName);
    }

    public function test_mas_de_3_lineas_se_rechaza(): void
    {
        $this->assertDomainError('VALIDATION_ERROR', fn () => $this->lend([
            $this->book('b1'), $this->book('b2'), $this->book('b3'), $this->book('b4'),
        ]));
    }

    public function test_libro_repetido_se_rechaza(): void
    {
        $book = $this->book('b1');
        $this->assertDomainError('DUPLICATE_BOOK_IN_LOAN', fn () => $this->lend([$book, $book]));
        $this->assertSame(2, $book->availableCopies()); // no descontó nada
    }

    public function test_socio_no_puede_pasar_de_3_sin_devolver(): void
    {
        $this->assertDomainError('MEMBER_LIMIT_EXCEEDED', fn () => $this->lend([$this->book('b1'), $this->book('b2')], unreturned: 2));
    }

    public function test_socio_con_vencidos_no_pide_mas(): void
    {
        $this->assertDomainError('MEMBER_HAS_OVERDUE', fn () => $this->lend([$this->book('b1')], overdue: true));
    }

    public function test_estado_open_recien_prestado(): void
    {
        $this->assertSame(LoanStatus::Open, $this->lend([$this->book('b1')])->status($this->now));
    }

    public function test_estado_overdue_pasados_14_dias(): void
    {
        $loan = $this->lend([$this->book('b1')]);
        $later = $this->now->modify('+15 days');
        $this->assertSame(LoanStatus::Overdue, $loan->status($later));
        $this->assertSame(1, $loan->items()[0]->daysLate($later));
    }

    public function test_estado_closed_cuando_todo_esta_devuelto(): void
    {
        $book = $this->book('b1', 1);
        $loan = $this->lend([$book]);
        $loan->returnItem($loan->items()[0]->id, $book, $this->now->modify('+3 days'));
        $this->assertSame(LoanStatus::Closed, $loan->status($this->now->modify('+30 days')));
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_devolver_dos_veces_no_suma_dos_veces(): void
    {
        $book = $this->book('b1', 1);
        $loan = $this->lend([$book]);
        $itemId = $loan->items()[0]->id;
        $loan->returnItem($itemId, $book, $this->now);
        $this->assertDomainError('ITEM_ALREADY_RETURNED', fn () => $loan->returnItem($itemId, $book, $this->now));
        $this->assertSame(1, $book->availableCopies());
    }
}
