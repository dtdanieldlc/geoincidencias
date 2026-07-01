<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificarPermiso
{
    /**
     * Uso en rutas: middleware('permiso:incidencias,editar')
     * Parámetros: modulo, accion (ver|editar|eliminar)
     */
    public function handle(Request $request, Closure $next, string $modulo, string $accion = 'ver')
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$usuario->puedeEn($modulo, $accion)) {
            return response()->json([
                'ok'      => false,
                'mensaje' => "No tienes permiso para {$accion} en el módulo '{$modulo}'.",
                'modulo'  => $modulo,
                'accion'  => $accion,
            ], 403);
        }

        return $next($request);
    }
}
