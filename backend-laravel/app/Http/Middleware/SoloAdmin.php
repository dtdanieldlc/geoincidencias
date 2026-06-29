<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SoloAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken || $accessToken->tokenable->rol !== 'admin') {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido a administradores.'], 403);
        }

        // ✅ Autenticar el usuario en el request
        $request->setUserResolver(fn() => $accessToken->tokenable);

        return $next($request);
    }
}