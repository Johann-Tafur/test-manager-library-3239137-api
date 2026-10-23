<?php

namespace App\Adapters\Inbound\Http;

use App\Domain\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * RNF-4 · Único lugar que traduce errores al cuerpo del contrato (architecture.md §5):
 * { "code", "message", "details" }. Nunca incluye trazas, SQL ni rutas del servidor.
 */
final class ApiErrorRenderer
{
    /** Estado HTTP de cada código de negocio (api-contract.md §2). */
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function render(\Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            return self::body(self::STATUS[$e->errorCode] ?? 422, $e->errorCode, $e->getMessage(), $e->details);
        }

        if ($e instanceof ValidationException) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $details[] = ['field' => $field, 'issue' => $message];
                }
            }

            return self::body(422, 'VALIDATION_ERROR', 'Hay campos inválidos.', $details);
        }

        if ($e instanceof NotFoundHttpException) {
            return self::body(404, 'NOT_FOUND', 'El recurso no existe.');
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::body($e->getStatusCode(), 'HTTP_ERROR', 'La petición no se pudo atender.');
        }

        report($e); // se registra en el log del servidor, nunca en la respuesta

        return self::body(500, 'INTERNAL_ERROR', 'Ocurrió un error inesperado.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    private static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'details' => $details], $status);
    }
}
