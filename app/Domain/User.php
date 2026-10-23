<?php

namespace App\Domain;

/**
 * Agregado Usuario (architecture.md §3): operador interno con rol admin o librarian.
 * El dominio nunca ve la clave en claro, solo su hash (D-09, artículo IX).
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';

    private function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $role,
    ) {}

    /** CA-02.1 y CA-02.3 · Por la API solo se crean bibliotecarios; pedir 'admin' es 422. */
    public static function createLibrarian(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== self::ROLE_LIBRARIAN) {
            throw new DomainError('VALIDATION_ERROR', 'Por la API solo se pueden crear usuarios con rol librarian.', [
                ['field' => 'role', 'issue' => 'solo se admite librarian'],
            ]);
        }

        return new self($id, self::normalize($username), $passwordHash, self::ROLE_LIBRARIAN);
    }

    /** El admin inicial lo crea el arranque desde variables de entorno, nunca la API (D-10). */
    public static function bootstrapAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, self::normalize($username), $passwordHash, self::ROLE_ADMIN);
    }

    /** CA-02.2 · El nombre se guarda en minúsculas y recortado. */
    public static function normalize(string $username): string
    {
        $username = mb_strtolower(trim($username));
        if ($username === '') {
            throw new DomainError('VALIDATION_ERROR', 'El nombre de usuario es obligatorio.', [
                ['field' => 'username', 'issue' => 'vacío'],
            ]);
        }

        return $username;
    }
}
