<?php

use App\Adapters\Inbound\Http\ApiErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RNF-4 · Todo error de la API sale con el cuerpo único del contrato
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is('api/*') ? ApiErrorRenderer::render($e) : null);
    })->create();
