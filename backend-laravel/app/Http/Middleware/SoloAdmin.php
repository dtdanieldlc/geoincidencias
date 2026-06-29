<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SoloAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->rol !== 'admin') {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido a administradores.'], 403);
        }

        return $next($request);
    }
}