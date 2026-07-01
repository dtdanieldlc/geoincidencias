<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SoloSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->rol !== 'superadmin') {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido al superadministrador.'], 403);
        }

        return $next($request);
    }
}
