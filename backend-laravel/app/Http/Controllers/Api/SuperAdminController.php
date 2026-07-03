<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/usuarios
       Lista TODOS los usuarios con email visible (sin password)
    ══════════════════════════════════════════════════════ */
    public function usuarios(Request $request)
    {
        $query = Usuario::query()->select([
            'id_usuario', 'nombre', 'apellido', 'correo', 'rol',
            'telefono', 'activo', 'saldo_incentivos',
            'correo_verificado', 'created_at',
            'ultima_presencia_at', 'ultima_pagina',
        ]);

        if ($buscar = $request->query('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('correo', 'like', "%{$buscar}%");
            });
        }

        if ($rol = $request->query('rol')) {
            $query->where('rol', $rol);
        }

        $usuarios = $query->orderByRaw("FIELD(rol,'superadmin','admin','usuario')")
                          ->orderBy('created_at', 'desc')
                          ->paginate(50);

        return response()->json(['ok' => true, 'data' => $usuarios]);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/usuarios/{id}/credenciales
       Muestra correo del usuario (password siempre hasheado,
       no se puede mostrar en texto plano por seguridad)
    ══════════════════════════════════════════════════════ */
    public function credenciales(int $id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->makeVisible(['respuesta_secreta']);

        return response()->json([
            'ok'   => true,
            'data' => [
                'id_usuario'            => $usuario->id_usuario,
                'nombre'                => $usuario->nombre,
                'apellido'              => $usuario->apellido,
                'correo'                => $usuario->correo,
                'telefono'              => $usuario->telefono,
                'cedula'                => $usuario->cedula,
                'rol'                   => $usuario->rol,
                'activo'                => $usuario->activo,
                'correo_verificado'     => $usuario->correo_verificado,
                'saldo_incentivos'      => $usuario->saldo_incentivos,
                'pregunta_secreta'      => $usuario->pregunta_secreta,
                'respuesta_secreta'     => $usuario->respuesta_secreta,
                'created_at'            => $usuario->created_at,
                'ultima_presencia_at'   => $usuario->ultima_presencia_at,
            ],
            'nota' => 'La contraseña está encriptada con bcrypt y no puede revertirse. Usa el campo de abajo para asignar una nueva.',
        ]);
    }

    /* ══════════════════════════════════════════════════════
       PUT /api/superadmin/usuarios/{id}/datos-completos
       SuperAdmin edita TODOS los datos de cualquier usuario,
       incluida la pregunta/respuesta secreta (recuperación de cuenta)
    ══════════════════════════════════════════════════════ */
    public function actualizarDatosCompletos(Request $request, int $id)
    {
        $usuario = Usuario::findOrFail($id);

        $data = $request->validate([
            'nombre'            => 'required|string|max:100',
            'apellido'          => 'nullable|string|max:100',
            'correo'            => 'required|email|max:150|unique:usuarios,correo,' . $id . ',id_usuario',
            'telefono'          => 'nullable|string|max:20',
            'cedula'            => 'nullable|string|max:10',
            'pregunta_secreta'  => 'nullable|string|max:255',
            'respuesta_secreta' => 'nullable|string|max:255',
        ]);

        $usuario->update($data);

        HistorialActividad::registrar(
            $request->user()->id_usuario, null, 'superadmin_editar_datos',
            "SuperAdmin editó los datos completos de {$usuario->nombre_completo} ({$usuario->correo})",
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Datos actualizados correctamente.']);
    }

    /* ══════════════════════════════════════════════════════
       PUT /api/superadmin/usuarios/{id}/rol
       Superadmin puede asignar cualquier rol
    ══════════════════════════════════════════════════════ */
    public function cambiarRol(Request $request, int $id)
    {
        $superadmin = $request->user();

        if (! in_array($request->rol, ['superadmin', 'admin', 'usuario'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Rol inválido.'], 400);
        }

        $usuario = Usuario::findOrFail($id);

        if ($usuario->id_usuario === $superadmin->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes cambiar tu propio rol.'], 400);
        }

        $rolAnterior  = $usuario->rol;
        $usuario->rol = $request->rol;
        $usuario->save();

        HistorialActividad::registrar(
            $superadmin->id_usuario, null, 'superadmin_cambiar_rol',
            "SuperAdmin cambió rol de usuario #{$id} ({$usuario->correo}) de {$rolAnterior} a {$request->rol}",
            $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => "Rol actualizado a {$request->rol}.",
            'rol'     => $usuario->rol,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       DELETE /api/superadmin/usuarios/{id}
       Eliminar cualquier usuario (excepto superadmin)
    ══════════════════════════════════════════════════════ */
    public function eliminar(Request $request, int $id)
    {
        $superadmin = $request->user();
        $usuario    = Usuario::findOrFail($id);

        if ($usuario->rol === 'superadmin') {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes eliminar al superadmin.'], 403);
        }

        if ($usuario->id_usuario === $superadmin->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes eliminarte a ti mismo.'], 400);
        }

        $nombre = $usuario->nombre_completo;
        $correo = $usuario->correo;
        $usuario->delete();

        HistorialActividad::registrar(
            $superadmin->id_usuario, null, 'superadmin_eliminar_usuario',
            "SuperAdmin eliminó al usuario {$nombre} ({$correo})",
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => "Usuario {$nombre} eliminado."]);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/logs
       Historial de actividad completo con paginación
    ══════════════════════════════════════════════════════ */
    public function logs(Request $request)
    {
        $query = HistorialActividad::with('usuario:id_usuario,nombre,apellido,correo,rol')
            ->orderBy('created_at', 'desc');

        if ($accion = $request->query('accion')) {
            $query->where('accion', 'like', "%{$accion}%");
        }

        if ($idUsuario = $request->query('id_usuario')) {
            $query->where('id_usuario', $idUsuario);
        }

        $logs = $query->paginate(50);

        return response()->json(['ok' => true, 'data' => $logs]);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/conectados
       Usuarios conectados en los últimos 5 minutos
    ══════════════════════════════════════════════════════ */
    public function conectados()
    {
        $desde = now()->subMinutes(5);

        $conectados = Usuario::select([
            'id_usuario', 'nombre', 'apellido', 'correo', 'rol',
            'ultima_presencia_at', 'ultima_pagina',
        ])
        ->where('ultima_presencia_at', '>=', $desde)
        ->orderBy('ultima_presencia_at', 'desc')
        ->get()
        ->map(fn ($u) => [
            'id_usuario'          => $u->id_usuario,
            'nombre'              => $u->nombre_completo,
            'correo'              => $u->correo,
            'rol'                 => $u->rol,
            'ultima_pagina'       => $u->ultima_pagina,
            'ultima_presencia_at' => $u->ultima_presencia_at,
            'hace'                => $u->ultima_presencia_at
                                       ? $u->ultima_presencia_at->diffForHumans()
                                       : 'desconocido',
        ]);

        return response()->json(['ok' => true, 'conectados' => $conectados, 'total' => $conectados->count()]);
    }

    /* ══════════════════════════════════════════════════════
       PUT /api/superadmin/usuarios/{id}/password
       Resetear contraseña de cualquier usuario
    ══════════════════════════════════════════════════════ */
    public function resetPassword(Request $request, int $id)
    {
        $request->validate(['password' => 'required|string|min:8']);

        $usuario           = Usuario::findOrFail($id);
        $usuario->password = bcrypt($request->password);
        $usuario->save();

        HistorialActividad::registrar(
            $request->user()->id_usuario, null, 'superadmin_reset_password',
            "SuperAdmin reseteó contraseña de usuario #{$id} ({$usuario->correo})",
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }
}
