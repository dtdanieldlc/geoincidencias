<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\ReporteUsuario;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReporteUsuarioController extends Controller
{
    /* ══════════════════════════════════════════════════════
       POST /api/usuarios/{id}/reportar
       Cualquier usuario autenticado puede reportar/denunciar a otro.
    ══════════════════════════════════════════════════════ */
    public function reportar(Request $request, int $id)
    {
        $yo = $request->user();

        if ($id === $yo->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes reportarte a ti mismo.'], 400);
        }

        $reportado = Usuario::find($id);
        if (! $reportado) {
            return response()->json(['ok' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'motivo'      => 'required|in:' . implode(',', array_keys(ReporteUsuario::MOTIVOS)),
            'descripcion' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Debes indicar un motivo válido.'], 400);
        }

        ReporteUsuario::create([
            'id_usuario_reportado'  => $id,
            'id_usuario_reportante' => $yo->id_usuario,
            'motivo'                => $request->motivo,
            'descripcion'           => $request->descripcion,
        ]);

        HistorialActividad::registrar(
            $yo->id_usuario, null, 'reportar_usuario',
            "{$yo->nombre} reportó a {$reportado->nombre} ({$reportado->correo}) — motivo: " . ReporteUsuario::MOTIVOS[$request->motivo],
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Reporte enviado. El equipo de administración lo va a revisar.']);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/reportes-usuario
       Lista de usuarios reportados, agrupados, con el conteo de
       denuncias (para el panel del superadmin). Filtra por estado
       con ?estado=pendiente|revisado|descartado|todos
    ══════════════════════════════════════════════════════ */
    public function panelReportes(Request $request)
    {
        $estado = $request->query('estado', 'pendiente');

        $query = ReporteUsuario::query()
            ->selectRaw('id_usuario_reportado, COUNT(*) as total_reportes, MAX(created_at) as ultimo_reporte_at')
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->groupBy('id_usuario_reportado')
            ->orderByDesc('total_reportes');

        $agrupado = $query->get();

        $datos = $agrupado->map(function ($fila) use ($estado) {
            $usuario = Usuario::select(['id_usuario', 'nombre', 'apellido', 'correo', 'rol', 'activo', 'foto_url'])
                ->find($fila->id_usuario_reportado);
            if (! $usuario) return null;

            return [
                'id_usuario'        => $usuario->id_usuario,
                'nombre'            => trim($usuario->nombre . ' ' . ($usuario->apellido ?? '')),
                'correo'            => $usuario->correo,
                'rol'               => $usuario->rol,
                'activo'            => (bool) $usuario->activo,
                'foto_url'          => $usuario->foto_url,
                'total_reportes'    => (int) $fila->total_reportes,
                'ultimo_reporte_at' => $fila->ultimo_reporte_at,
            ];
        })->filter()->values();

        return response()->json($datos);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/superadmin/reportes-usuario/usuario/{id}
       Detalle: todas las denuncias que tiene un usuario puntual.
    ══════════════════════════════════════════════════════ */
    public function detalleUsuario(int $id)
    {
        $reportes = ReporteUsuario::where('id_usuario_reportado', $id)
            ->with(['reportante:id_usuario,nombre,apellido,correo', 'adminRevisor:id_usuario,nombre,apellido'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id_reporte'    => $r->id_reporte,
                'motivo'        => $r->motivo,
                'motivo_label'  => ReporteUsuario::MOTIVOS[$r->motivo] ?? $r->motivo,
                'descripcion'   => $r->descripcion,
                'estado'        => $r->estado,
                'reportante'    => $r->reportante ? trim($r->reportante->nombre . ' ' . ($r->reportante->apellido ?? '')) : 'Usuario eliminado',
                'reportante_correo' => $r->reportante->correo ?? null,
                'admin_revisor' => $r->adminRevisor ? trim($r->adminRevisor->nombre . ' ' . ($r->adminRevisor->apellido ?? '')) : null,
                'created_at'    => $r->created_at,
                'revisado_at'   => $r->revisado_at,
            ]);

        return response()->json($reportes);
    }

    /* ══════════════════════════════════════════════════════
       PUT /api/superadmin/reportes-usuario/{id}/estado   { estado }
       El superadmin marca un reporte puntual como revisado/descartado.
    ══════════════════════════════════════════════════════ */
    public function cambiarEstado(Request $request, int $id)
    {
        $reporte = ReporteUsuario::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:pendiente,revisado,descartado',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Estado inválido.'], 400);
        }

        $reporte->estado       = $request->estado;
        $reporte->revisado_at  = now();
        $reporte->id_admin_revisor = $request->user()->id_usuario;
        $reporte->save();

        return response()->json(['ok' => true, 'mensaje' => 'Reporte actualizado.']);
    }
}
