<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;

class AdminUsuariosController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    //  GET /api/admin/usuarios
    // ──────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Usuario::query()->select([
            'id_usuario', 'nombre', 'apellido', 'correo', 'rol',
            'telefono', 'activo', 'saldo_incentivos',
            'correo_verificado', 'correo_verificado_at', 'created_at',
            'ultima_presencia_at', 'ultima_pagina',
        ]);

        if ($buscar = $request->query('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('correo', 'like', "%{$buscar}%")
                  ->orWhere('id_usuario', (int) $buscar ?: 0);
            });
        }

        if ($request->has('activo') && $request->query('activo') !== '') {
            $query->where('activo', filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($rol = $request->query('rol')) {
            $query->where('rol', $rol);
        }

        if ($request->has('verificado') && $request->query('verificado') !== '') {
            $query->where('correo_verificado', filter_var($request->query('verificado'), FILTER_VALIDATE_BOOLEAN));
        }

        $porPagina = min((int) ($request->query('por_pagina', 20)), 100);
        $usuarios  = $query->orderBy('created_at', 'desc')->paginate($porPagina);

        return response()->json(['ok' => true, 'data' => $usuarios]);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/admin/usuarios/{id}
    // ──────────────────────────────────────────────────────────────
    public function show(int $id)
    {
        $usuario = Usuario::findOrFail($id);
        return response()->json([
            'ok'   => true,
            'data' => $usuario->makeVisible(['correo_verificado', 'correo_verificado_at', 'created_at']),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/admin/usuarios/{id}/activo
    // ──────────────────────────────────────────────────────────────
    public function toggleActivo(Request $request, int $id)
    {
        $admin   = $request->user();
        $usuario = Usuario::findOrFail($id);

        if ($usuario->id_usuario === $admin->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes desactivar tu propia cuenta.'], 400);
        }

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        $estado = $usuario->activo ? 'activado' : 'desactivado';

        HistorialActividad::registrar(
            $admin->id_usuario, null, 'admin_toggle_usuario',
            "Admin {$admin->nombre} {$estado} usuario #{$id} ({$usuario->correo})", $request->ip()
        );

        return response()->json([
            'ok'     => true,
            'mensaje' => "Usuario {$estado} correctamente.",
            'activo' => $usuario->activo,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/admin/usuarios/{id}/rol
    // ──────────────────────────────────────────────────────────────
    public function cambiarRol(Request $request, int $id)
    {
        $admin = $request->user();

        if (! in_array($request->rol, ['admin', 'usuario'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Rol inválido.'], 400);
        }

        $usuario = Usuario::findOrFail($id);

        if ($usuario->id_usuario === $admin->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes cambiar tu propio rol.'], 400);
        }

        $rolAnterior  = $usuario->rol;
        $usuario->rol = $request->rol;
        $usuario->save();

        HistorialActividad::registrar(
            $admin->id_usuario, null, 'admin_cambiar_rol',
            "Admin {$admin->nombre} cambió rol de usuario #{$id} de {$rolAnterior} a {$request->rol}", $request->ip()
        );

        return response()->json([
            'ok'     => true,
            'mensaje' => "Rol actualizado a {$request->rol}.",
            'rol'    => $usuario->rol,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/admin/usuarios/estadisticas
    // ──────────────────────────────────────────────────────────────
    public function estadisticas()
    {
        // Usuarios online = última presencia hace menos de 60 segundos
        $onlineDesde = now()->subSeconds(60);

        return response()->json([
            'ok'   => true,
            'data' => [
                'total'           => Usuario::count(),
                'activos'         => Usuario::where('activo', true)->count(),
                'inactivos'       => Usuario::where('activo', false)->count(),
                'verificados'     => Usuario::where('correo_verificado', true)->count(),
                'sin_verificar'   => Usuario::where('correo_verificado', false)->count(),
                'admins'          => Usuario::where('rol', 'admin')->count(),
                'nuevos_este_mes' => Usuario::whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year)
                                            ->count(),
                'online_ahora'    => Usuario::where('ultima_presencia_at', '>=', $onlineDesde)->count(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/admin/usuarios/{id}/presencia
    //  Body: { pagina: "dashboard.html" }
    //  Llamado cada 30 s por el heartbeat del frontend
    // ──────────────────────────────────────────────────────────────
    public function actualizarPresencia(Request $request, int $id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->ultima_presencia_at = now();
        $usuario->ultima_pagina       = $request->input('pagina', '');
        $usuario->save();

        return response()->json(['ok' => true]);
    }
}
