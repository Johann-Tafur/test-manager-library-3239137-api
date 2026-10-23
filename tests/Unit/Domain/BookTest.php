<?php

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/** Evidencia de T-04: los disponibles nunca bajan de 0 ni pasan del total (RN-1). */
class BookTest extends TestCase
{
    private function book(int $copies): Book
    {
        return Book::create('b-1', 'Cien años de soledad', 'Gabriel García Márquez', new Isbn('9780307474728'), 'cat-1', new Quantity($copies));
    }

    public function test_al_crear_los_disponibles_son_iguales_al_total(): void
    {
        $this->assertSame(4, $this->book(4)->availableCopies());
    }

    public function test_sin_ejemplares_lanza_book_not_available_y_no_baja_de_cero(): void
    {
        $book = $this->book(1);
        $book->withdraw();
        try {
            $book->withdraw();
            $this->fail('Debió lanzar BOOK_NOT_AVAILABLE');
        } catch (DomainError $e) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $e->errorCode);
        }
        $this->assertSame(0, $book->availableCopies());
    }

    public function test_restock_nunca_supera_el_total(): void
    {
        $this->expectException(\LogicException::class);
        $this->book(1)->restock();
    }

    public function test_cambiar_total_ajusta_disponibles_en_la_misma_diferencia(): void
    {
        $book = $this->book(3);
        $book->withdraw();               // 2 disponibles de 3
        $book->changeTotal(new Quantity(5));
        $this->assertSame(4, $book->availableCopies());
    }

    public function test_cambiar_total_por_debajo_de_lo_prestado_se_rechaza(): void
    {
        $book = $this->book(2);
        $book->withdraw();
        $book->withdraw();               // 0 disponibles, 2 prestados
        $this->expectException(DomainError::class);
        $book->changeTotal(new Quantity(1));
    }

    public function test_libro_con_prestamos_abiertos_no_se_da_de_baja(): void
    {
        try {
            $this->book(1)->delete(new \DateTimeImmutable(), hasOpenLoans: true);
            $this->fail('Debió lanzar BOOK_HAS_OPEN_LOANS');
        } catch (DomainError $e) {
            $this->assertSame('BOOK_HAS_OPEN_LOANS', $e->errorCode);
        }
    }

    public function test_baja_logica_marca_el_libro_como_eliminado(): void
    {
        $book = $this->book(1);
        $book->delete(new \DateTimeImmutable('2026-10-22T10:00:00Z'), hasOpenLoans: false);
        $this->assertTrue($book->isDeleted());
    }
}
