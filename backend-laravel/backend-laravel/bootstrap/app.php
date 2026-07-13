<?php

use App\Http\Middleware\SoloAdmin;
use App\Http\Middleware\SoloSuperAdmin;
use App\Http\Middleware\VerificarPermiso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Esta es una API pura (sin rutas web/sesión), así que nunca debe
        // intentar redirigir a una ruta "login" que no existe cuando la
        // autenticación falla. En vez de eso, siempre debe responder 401 en JSON.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'solo.admin'      => SoloAdmin::class,
            'solo.superadmin' => SoloSuperAdmin::class,
            'permiso'         => VerificarPermiso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Todo lo que empiece con /api siempre responde en JSON, sin importar
        // qué "Accept" mande el cliente (navegador, Postman, curl, etc.).
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
