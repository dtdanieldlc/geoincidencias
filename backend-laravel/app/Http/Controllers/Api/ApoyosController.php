<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Incidencia;
use App\Models\IncentivoPrioridad;
use App\Models\IncidenciaApoyo;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApoyosController extends Controller
{
    // Selección base equivalente a vw_apoyos_completo
    private function baseQuery()
    {
        return IncidenciaApoyo::query()
            ->join('incidencias as i', 'incidencia_apoyos.id_incidencia', '=', 'i.id_incidencia')
            ->join('usuarios as u', 'incidencia_apoyos.id_usuario', '=', 'u.id_usuario')
            ->select([
                'incidencia_apoyos.id_apoyo', 'incidencia_apoyos.id_incidencia',
                'i.titulo as incidencia_titulo', 'i.prioridad',
                DB::raw("CONCAT(u.nombre,' ',IFNULL(u.apellido,'')) as usuario"),
                'u.id_usuario',
                'incidencia_apoyos.monto_incentivo', 'incidencia_apoyos.estado_pago',
                'incidencia_apoyos.comentario_usuario', 'incidencia_apoyos.comentario_admin',
                'incidencia_apoyos.fecha_apoyo', 'incidencia_apoyos.fecha_revision', 'incidencia_apoyos.fecha_pago',
            ]);
    }

    // POST /api/apoyos
    public function store(Request $request)
    {
        $usuario = $request->user();
        $idIncidencia = $request->input('id_incidencia');

        if (! $idIncidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la incidencia.'], 400);
        }

        $existe = IncidenciaApoyo::where('id_incidencia', $idIncidencia)
            ->where('id_usuario', $usuario->id_usuario)->exists();
        if ($existe) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya marcaste apoyo en esta incidencia.'], 400);
        }

        $incidencia = Incidencia::find($idIncidencia);
        if (! $incidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada.'], 404);
        }

        $incentivo = IncentivoPrioridad::where('prioridad', $incidencia->prioridad)->first();
        $monto = $incentivo?->monto ?? 0;

        $apoyo = IncidenciaApoyo::create([
            'id_incidencia' => $idIncidencia,
            'id_usuario' => $usuario->id_usuario,
            'monto_incentivo' => $monto,
            'comentario_usuario' => $request->input('comentario_usuario'),
        ]);

        $admins = Usuario::where('rol', 'admin')->where('activo', 1)->get();
        foreach ($admins as $admin) {
            Notificacion::create([
                'id_usuario' => $admin->id_usuario,
                'id_incidencia' => $idIncidencia,
                'titulo' => 'Nuevo apoyo por aprobar',
                'mensaje' => "{$usuario->nombre_completo} solicitó apoyar en \"{$incidencia->titulo}\" (\${$monto}).",
            ]);
        }

        HistorialActividad::registrar(
            $usuario->id_usuario, $idIncidencia, 'marco_apoyo',
            "{$usuario->nombre_completo} marcó apoyo voluntario en \"{$incidencia->titulo}\" (incentivo: \${$monto}, pendiente de aprobación)",
            $request->ip()
        );

        return response()->json([
            'ok' => true,
            'mensaje' => "Apoyo registrado. Incentivo de \${$monto} pendiente de aprobación.",
            'id' => $apoyo->id_apoyo,
        ], 201);
    }

    // GET /api/apoyos/mis-apoyos
    public function misApoyos(Request $request)
    {
        $datos = $this->baseQuery()
            ->where('incidencia_apoyos.id_usuario', $request->user()->id_usuario)
            ->orderByDesc('incidencia_apoyos.fecha_apoyo')
            ->get();

        return response()->json($datos);
    }

    // GET /api/apoyos/mi-saldo
    public function miSaldo(Request $request)
    {
        $idUsuario = $request->user()->id_usuario;

        $totalPagado = IncidenciaApoyo::where('id_usuario', $idUsuario)
            ->where('estado_pago', 'pagado')->sum('monto_incentivo');
        $totalPendiente = IncidenciaApoyo::where('id_usuario', $idUsuario)
            ->where('estado_pago', 'pendiente_aprobacion')->sum('monto_incentivo');
        $apoyosCompletados = IncidenciaApoyo::where('id_usuario', $idUsuario)
            ->where('estado_pago', 'pagado')->count();

        return response()->json([
            'total_pagado' => $totalPagado,
            'total_pendiente' => $totalPendiente,
            'apoyos_completados' => $apoyosCompletados,
        ]);
    }

    // GET /api/apoyos/pendientes (solo admin)
    public function pendientes()
    {
        $datos = $this->baseQuery()
            ->where('incidencia_apoyos.estado_pago', 'pendiente_aprobacion')
            ->orderBy('incidencia_apoyos.fecha_apoyo')
            ->get();

        return response()->json($datos);
    }

    // GET /api/apoyos (solo admin, con filtro)
    public function index(Request $request)
    {
        $query = $this->baseQuery();
        if ($estado = $request->query('estado_pago')) {
            $query->where('incidencia_apoyos.estado_pago', $estado);
        }
        return response()->json($query->orderByDesc('incidencia_apoyos.fecha_apoyo')->get());
    }

    // PUT /api/apoyos/{id}/aprobar (solo admin)
    public function aprobar(Request $request, $id)
    {
        $apoyo = IncidenciaApoyo::find($id);
        if (! $apoyo) {
            return response()->json(['ok' => false, 'mensaje' => 'Apoyo no encontrado.'], 404);
        }
        if ($apoyo->estado_pago !== 'pendiente_aprobacion') {
            return response()->json(['ok' => false, 'mensaje' => 'Este apoyo ya fue revisado.'], 400);
        }

        $usuario = $request->user();

        $apoyo->update([
            'estado_pago' => 'pagado',
            'id_admin_revisor' => $usuario->id_usuario,
            'comentario_admin' => $request->input('comentario_admin'),
            'fecha_revision' => now(),
            'fecha_pago' => now(),
        ]);

        Usuario::where('id_usuario', $apoyo->id_usuario)
            ->increment('saldo_incentivos', $apoyo->monto_incentivo);

        Notificacion::create([
            'id_usuario' => $apoyo->id_usuario,
            'id_incidencia' => $apoyo->id_incidencia,
            'titulo' => 'Incentivo pagado',
            'mensaje' => "Tu apoyo fue aprobado. Se pagaron \${$apoyo->monto_incentivo} a tu saldo.",
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $apoyo->id_incidencia, 'aprobo_apoyo',
            "Admin {$usuario->nombre_completo} aprobó y pagó \${$apoyo->monto_incentivo} de incentivo (apoyo #{$id})",
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => "Apoyo aprobado. Se pagaron \${$apoyo->monto_incentivo}."]);
    }

    // PUT /api/apoyos/{id}/rechazar (solo admin)
    public function rechazar(Request $request, $id)
    {
        $apoyo = IncidenciaApoyo::find($id);
        if (! $apoyo) {
            return response()->json(['ok' => false, 'mensaje' => 'Apoyo no encontrado.'], 404);
        }

        $usuario = $request->user();
        $comentario = $request->input('comentario_admin');

        $apoyo->update([
            'estado_pago' => 'rechazado',
            'id_admin_revisor' => $usuario->id_usuario,
            'comentario_admin' => $comentario,
            'fecha_revision' => now(),
        ]);

        Notificacion::create([
            'id_usuario' => $apoyo->id_usuario,
            'id_incidencia' => $apoyo->id_incidencia,
            'titulo' => 'Apoyo rechazado',
            'mensaje' => 'Tu solicitud de apoyo no fue aprobada. Motivo: ' . ($comentario ?: 'No especificado'),
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $apoyo->id_incidencia, 'rechazo_apoyo',
            "Admin {$usuario->nombre_completo} rechazó el apoyo #{$id}. Motivo: " . ($comentario ?: 'N/A'),
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Apoyo rechazado.']);
    }
}
