<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Evidencia parcial de T-16 · RNF-4: todo error HTTP usa el mismo cuerpo (api-contract.md §1). */
class ApiContractTest extends TestCase
{
    public function test_health_responde_ok(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_ruta_inexistente_usa_el_cuerpo_unico_sin_trazas(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertStatus(404)
            ->assertExactJson(['code' => 'NOT_FOUND', 'message' => 'El recurso no existe.', 'details' => []]);
    }

    public function test_error_de_negocio_se_traduce_a_su_estado_y_codigo(): void
    {
        Route::get('/api/v1/_prueba', fn () => throw new DomainError('BOOK_NOT_AVAILABLE', 'Sin ejemplares.'));

        $this->getJson('/api/v1/_prueba')
            ->assertStatus(409)
            ->assertJson(['code' => 'BOOK_NOT_AVAILABLE', 'message' => 'Sin ejemplares.', 'details' => []]);
    }
}
