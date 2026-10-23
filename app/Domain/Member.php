<?php

namespace App\Domain;

/**
 * Agregado Socio (architecture.md §3). No inicia sesión: es un dato, no un usuario.
 * CA-05.1 · Nombre y documento obligatorios. CA-05.3 · No se eliminan (no hay método para eso).
 */
final class Member
{
    private function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly string $documentNumber,
    ) {}

    public static function create(string $id, string $fullName, string $documentNumber): self
    {
        $fullName = trim($fullName);
        $documentNumber = trim($documentNumber);

        if ($fullName === '') {
            throw new DomainError('VALIDATION_ERROR', 'El nombre del socio es obligatorio.', [
                ['field' => 'full_name', 'issue' => 'vacío'],
            ]);
        }
        if ($documentNumber === '' || mb_strlen($documentNumber) > 30) {
            throw new DomainError('VALIDATION_ERROR', 'El documento es obligatorio y tiene máximo 30 caracteres.', [
                ['field' => 'document_number', 'issue' => 'vacío o demasiado largo'],
            ]);
        }

        return new self($id, $fullName, $documentNumber);
    }
}
