<?php

namespace Tests\Unit\Domain;

use App\Domain\DomainError;
use App\Domain\ValueObjects\DateRange;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/** Evidencia de T-03: ISBN de 13 dígitos, cantidad mayor que 0, fin mayor o igual que inicio. */
class ValueObjectsTest extends TestCase
{
    public function test_isbn_valido_de_13_digitos(): void
    {
        $this->assertSame('9780307474728', (new Isbn(' 9780307474728 '))->value);
    }

    public function test_isbn_con_menos_de_13_digitos_se_rechaza(): void
    {
        $this->expectException(DomainError::class);
        new Isbn('12345');
    }

    public function test_isbn_con_letras_se_rechaza(): void
    {
        $this->expectException(DomainError::class);
        new Isbn('978030747472X');
    }

    public function test_cantidad_cero_se_rechaza(): void
    {
        $this->expectException(DomainError::class);
        new Quantity(0);
    }

    public function test_rango_con_fin_anterior_al_inicio_da_invalid_date_range(): void
    {
        try {
            new DateRange(new \DateTimeImmutable('2026-10-31'), new \DateTimeImmutable('2026-10-01'));
            $this->fail('Debió lanzar DomainError');
        } catch (DomainError $e) {
            $this->assertSame('INVALID_DATE_RANGE', $e->errorCode);
        }
    }
}
