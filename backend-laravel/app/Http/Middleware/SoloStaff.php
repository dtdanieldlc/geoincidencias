<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Admin, Superadmin o Encargado de departamento */
class SoloStaff
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();
        if (! $usuario || ! in_array($usuario->rol, ['admin', 'superadmin', 'encargado'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido al personal autorizado.'], 403);
        }
        return $next($request);
    }
}
