<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPermiso;
use App\Models\HistorialActividad;
use App\Models\SolicitudPermiso;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermisosController extends Controller
{
    private array $modulosValidos;

    public function __construct()
    {
        $this->modulosValidos = AdminPermiso::modulosDisponibles();
    }

    /* ══════════════════════════════════════════════════════
       SUPERADMIN — asignación directa de permisos
    ══════════════════════════════════════════════════════ */

    // GET /api/superadmin/permisos/{id_usuario}
    // Devuelve todos los permisos de un admin con todos los módulos
    public function permisosDeUsuario(int $idUsuario)
    {
        $usuario = Usuario::findOrFail($idUsuario);

        $permisos = AdminPermiso::with('otorgadoPor:id_usuario,nombre,apellido')
            ->where('id_usuario', $idUsuario)
            ->get()
            ->keyBy('modulo');

        // Devuelve todos los módulos, tengan permiso o no
        $resultado = collect($this->modulosValidos)->map(function ($modulo) use ($permisos, $usuario) {
            if ($permisos->has($modulo)) {
                return $permisos[$modulo];
            }
            return [
                'modulo'         => $modulo,
                'puede_ver'      => false,
                'puede_editar'   => false,
                'puede_eliminar' => false,
                'otorgado_por'   => null,
                'created_at'     => null,
            ];
        });

        return response()->json([
            'ok'      => true,
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre'     => $usuario->nombre_completo,
                'correo'     => $usuario->correo,
                'rol'        => $usuario->rol,
            ],
            'permisos' => $resultado->values(),
        ]);
    }

    // PUT /api/superadmin/permisos/{id_usuario}
    // Asignación directa — reemplaza todos los permisos del usuario
    public function asignarPermisos(Request $request, int $idUsuario)
    {
        $superadmin = $request->user();
        $usuario    = Usuario::findOrFail($idUsuario);

        if ($usuario->rol === 'superadmin') {
            return response()->json(['ok' => false, 'mensaje' => 'El superadmin no necesita permisos asignados.'], 400);
        }

        if ($usuario->rol !== 'admin') {
            return response()->json(['ok' => false, 'mensaje' => 'Solo se pueden asignar permisos a usuarios con rol admin.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'permisos'                    => 'required|array',
            'permisos.*.modulo'           => 'required|string|in:' . implode(',', $this->modulosValidos),
            'permisos.*.puede_ver'        => 'required|boolean',
            'permisos.*.puede_editar'     => 'required|boolean',
            'permisos.*.puede_eliminar'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        // Elimina permisos anteriores y reinserta
        AdminPermiso::where('id_usuario', $idUsuario)->delete();

        $insertados = 0;
        foreach ($request->permisos as $p) {
            // Solo insertar si tiene al menos un permiso activo
            if ($p['puede_ver'] || $p['puede_editar'] || $p['puede_eliminar']) {
                AdminPermiso::create([
                    'id_usuario'     => $idUsuario,
                    'modulo'         => $p['modulo'],
                    'puede_ver'      => $p['puede_ver'],
                    'puede_editar'   => $p['puede_editar'],
                    'puede_eliminar' => $p['puede_eliminar'],
                    'otorgado_por'   => $superadmin->id_usuario,
                ]);
                $insertados++;
            }
        }

        HistorialActividad::registrar(
            $superadmin->id_usuario, null, 'superadmin_asignar_permisos',
            "SuperAdmin asignó {$insertados} módulos a {$usuario->nombre_completo} ({$usuario->correo})",
            $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => "Permisos actualizados. {$insertados} módulo(s) activos.",
        ]);
    }

    // GET /api/superadmin/permisos/modulos
    // Lista los módulos disponibles
    public function modulos()
    {
        return response()->json([
            'ok'      => true,
            'modulos' => $this->modulosValidos,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       ADMIN — solicitar permisos con motivo
    ══════════════════════════════════════════════════════ */

    // POST /api/admin/solicitudes-permisos
    public function solicitarPermisos(Request $request)
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'id_usuario_objetivo'             => 'required|integer|exists:usuarios,id_usuario',
            'motivo'                          => 'required|string|min:10|max:1000',
            'permisos_solicitados'            => 'required|array|min:1',
            'permisos_solicitados.*.modulo'   => 'required|string|in:' . implode(',', $this->modulosValidos),
            'permisos_solicitados.*.puede_ver'      => 'required|boolean',
            'permisos_solicitados.*.puede_editar'   => 'required|boolean',
            'permisos_solicitados.*.puede_eliminar' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $objetivo = Usuario::findOrFail($request->id_usuario_objetivo);

        if ($objetivo->rol === 'superadmin') {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes solicitar permisos para el superadmin.'], 400);
        }

        // Verificar que no haya una solicitud pendiente para el mismo usuario
        $pendiente = SolicitudPermiso::where('id_admin_solicitante', $admin->id_usuario)
            ->where('id_usuario_objetivo', $request->id_usuario_objetivo)
            ->where('estado', 'pendiente')
            ->first();

        if ($pendiente) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Ya tienes una solicitud pendiente para este usuario. Espera a que sea revisada.',
            ], 400);
        }

        $solicitud = SolicitudPermiso::create([
            'id_admin_solicitante' => $admin->id_usuario,
            'id_usuario_objetivo'  => $request->id_usuario_objetivo,
            'permisos_solicitados' => $request->permisos_solicitados,
            'motivo'               => trim($request->motivo),
            'estado'               => 'pendiente',
        ]);

        HistorialActividad::registrar(
            $admin->id_usuario, null, 'admin_solicitar_permisos',
            "Admin {$admin->nombre_completo} solicitó permisos para usuario #{$request->id_usuario_objetivo} ({$objetivo->nombre_completo}). Motivo: {$request->motivo}",
            $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Solicitud enviada al superadmin para revisión.',
            'id'      => $solicitud->id,
        ], 201);
    }

    // GET /api/admin/solicitudes-permisos
    // Admin ve sus propias solicitudes
    public function misSolicitudes(Request $request)
    {
        $admin = $request->user();

        $solicitudes = SolicitudPermiso::with([
            'usuarioObjetivo:id_usuario,nombre,apellido,correo,rol',
            'revisadoPor:id_usuario,nombre,apellido',
        ])
        ->where('id_admin_solicitante', $admin->id_usuario)
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return response()->json(['ok' => true, 'data' => $solicitudes]);
    }

    /* ══════════════════════════════════════════════════════
       SUPERADMIN — revisar solicitudes
    ══════════════════════════════════════════════════════ */

    // GET /api/superadmin/solicitudes-permisos
    public function todasLasSolicitudes(Request $request)
    {
        $query = SolicitudPermiso::with([
            'solicitante:id_usuario,nombre,apellido,correo',
            'usuarioObjetivo:id_usuario,nombre,apellido,correo,rol',
            'revisadoPor:id_usuario,nombre,apellido',
        ])->orderBy('created_at', 'desc');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        $solicitudes = $query->paginate(20);

        return response()->json(['ok' => true, 'data' => $solicitudes]);
    }

    // GET /api/superadmin/solicitudes-permisos/{id}
    public function detalleSolicitud(int $id)
    {
        $solicitud = SolicitudPermiso::with([
            'solicitante:id_usuario,nombre,apellido,correo',
            'usuarioObjetivo:id_usuario,nombre,apellido,correo,rol',
            'revisadoPor:id_usuario,nombre,apellido',
        ])->findOrFail($id);

        return response()->json(['ok' => true, 'data' => $solicitud]);
    }

    // PUT /api/superadmin/solicitudes-permisos/{id}/revisar
    // Aprobar, rechazar o modificar una solicitud
    public function revisarSolicitud(Request $request, int $id)
    {
        $superadmin = $request->user();

        $validator = Validator::make($request->all(), [
            'decision'                         => 'required|in:aprobado,rechazado,modificado',
            'respuesta'                        => 'nullable|string|max:1000',
            'permisos_aprobados'               => 'required_if:decision,aprobado,modificado|array',
            'permisos_aprobados.*.modulo'      => 'required_if:decision,aprobado,modificado|string',
            'permisos_aprobados.*.puede_ver'   => 'required_if:decision,aprobado,modificado|boolean',
            'permisos_aprobados.*.puede_editar'    => 'required_if:decision,aprobado,modificado|boolean',
            'permisos_aprobados.*.puede_eliminar'  => 'required_if:decision,aprobado,modificado|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $solicitud = SolicitudPermiso::findOrFail($id);

        if ($solicitud->estado !== 'pendiente') {
            return response()->json(['ok' => false, 'mensaje' => 'Esta solicitud ya fue revisada.'], 400);
        }

        $solicitud->estado              = $request->decision;
        $solicitud->respuesta_superadmin = $request->respuesta;
        $solicitud->revisado_por        = $superadmin->id_usuario;

        // Si se aprueba o modifica, aplicar los permisos al usuario
        if (in_array($request->decision, ['aprobado', 'modificado'])) {
            $permisosAplicar = $request->decision === 'modificado'
                ? $request->permisos_aprobados
                : $solicitud->permisos_solicitados;

            $solicitud->permisos_aprobados = $permisosAplicar;

            // Aplicar permisos al usuario objetivo
            $idObjetivo = $solicitud->id_usuario_objetivo;
            foreach ($permisosAplicar as $p) {
                AdminPermiso::updateOrCreate(
                    ['id_usuario' => $idObjetivo, 'modulo' => $p['modulo']],
                    [
                        'puede_ver'      => $p['puede_ver'],
                        'puede_editar'   => $p['puede_editar'],
                        'puede_eliminar' => $p['puede_eliminar'],
                        'otorgado_por'   => $superadmin->id_usuario,
                    ]
                );
            }
        }

        $solicitud->save();

        $objetivo = Usuario::find($solicitud->id_usuario_objetivo);
        HistorialActividad::registrar(
            $superadmin->id_usuario, null, "superadmin_{$request->decision}_permisos",
            "SuperAdmin {$request->decision} solicitud #{$id} de {$solicitud->solicitante?->nombre_completo} para {$objetivo?->nombre_completo}. " .
            ($request->respuesta ? "Respuesta: {$request->respuesta}" : ''),
            $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => "Solicitud {$request->decision} correctamente.",
        ]);
    }

    /* ══════════════════════════════════════════════════════
       CUALQUIER USUARIO AUTENTICADO
       GET /api/mis-permisos — para que el frontend sepa qué mostrar
    ══════════════════════════════════════════════════════ */
    public function misPermisos(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->esSuperAdmin()) {
            // Superadmin tiene acceso total a todo
            $permisos = collect(AdminPermiso::modulosDisponibles())->mapWithKeys(fn($m) => [
                $m => ['puede_ver' => true, 'puede_editar' => true, 'puede_eliminar' => true]
            ]);
            return response()->json(['ok' => true, 'rol' => 'superadmin', 'permisos' => $permisos]);
        }

        if ($usuario->rol === 'admin') {
            $permisos = AdminPermiso::where('id_usuario', $usuario->id_usuario)
                ->get()
                ->mapWithKeys(fn($p) => [
                    $p->modulo => [
                        'puede_ver'      => $p->puede_ver,
                        'puede_editar'   => $p->puede_editar,
                        'puede_eliminar' => $p->puede_eliminar,
                    ]
                ]);
            return response()->json(['ok' => true, 'rol' => 'admin', 'permisos' => $permisos]);
        }

        return response()->json(['ok' => true, 'rol' => $usuario->rol, 'permisos' => []]);
    }
}
