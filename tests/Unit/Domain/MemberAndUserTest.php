<?php

namespace Tests\Unit\Domain;

use App\Domain\DomainError;
use App\Domain\Member;
use App\Domain\User;
use PHPUnit\Framework\TestCase;

/** Evidencia de T-06: "Ana " queda "ana"; rol admin por API rechazado; socio con datos obligatorios. */
class MemberAndUserTest extends TestCase
{
    public function test_el_nombre_de_usuario_se_guarda_en_minusculas_y_recortado(): void
    {
        $user = User::createLibrarian('u-1', 'Ana ', 'hash', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame('librarian', $user->role);
    }

    public function test_pedir_rol_admin_por_la_api_se_rechaza(): void
    {
        try {
            User::createLibrarian('u-1', 'ana', 'hash', 'admin');
            $this->fail('Debió rechazar el rol admin');
        } catch (DomainError $e) {
            $this->assertSame('VALIDATION_ERROR', $e->errorCode);
        }
    }

    public function test_el_admin_inicial_solo_lo_crea_el_arranque(): void
    {
        $this->assertSame('admin', User::bootstrapAdmin('u-0', ' Admin', 'hash')->role);
    }

    public function test_socio_valido(): void
    {
        $member = Member::create('m-1', ' María Pérez ', ' 1020304050 ');
        $this->assertSame('María Pérez', $member->fullName);
        $this->assertSame('1020304050', $member->documentNumber);
    }

    public function test_socio_sin_documento_se_rechaza(): void
    {
        $this->expectException(DomainError::class);
        Member::create('m-1', 'María Pérez', '   ');
    }
}
