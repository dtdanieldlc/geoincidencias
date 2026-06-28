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
        if (!$token) {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido a administradores.'], 403);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || $accessToken->tokenable->rol !== 'admin') {
            return response()->json(['ok' => false, 'mensaje' => 'Acceso restringido a administradores.'], 403);
        }

        return $next($request);
    }
}