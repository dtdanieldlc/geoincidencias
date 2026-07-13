<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Horas máximas antes de considerar "vencida" una incidencia de prioridad
    // Alta que sigue sin resolverse. Ajustable según el SLA real del cliente.
    const SLA_HORAS_ALTA = 48;

    public function resumen()
    {
        $r = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->where('i.estado_aprobacion', 'aprobada')   // ← AGREGAR ESTO
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre='Pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN e.nombre='Pendiente' THEN 1 ELSE 0 END) as abiertas,
                SUM(CASE WHEN e.nombre='En proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN e.nombre='Resuelto' THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN e.nombre='Cerrado' THEN 1 ELSE 0 END) as cerradas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as alta_prioridad,
                SUM(CASE WHEN i.estado_aprobacion='pendiente_revision' THEN 1 ELSE 0 END) as pendientes_aprobacion,
                SUM(CASE WHEN i.prioridad='Alta' AND e.nombre NOT IN ('Resuelto','Cerrado')
                    AND TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) > " . self::SLA_HORAS_ALTA . "
                    THEN 1 ELSE 0 END) as vencidas
            ")
            ->first();

        return response()->json($r);
    }

    // GET /api/dashboard/vencidas
    // Incidencias de prioridad Alta que llevan más de SLA_HORAS_ALTA horas
    // sin resolverse — para el widget de "atención urgente" del dashboard.
    public function vencidas()
    {
        $datos = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->where('i.estado_aprobacion', 'aprobada')
            ->where('i.prioridad', 'Alta')
            ->whereNotIn('e.nombre', ['Resuelto', 'Cerrado'])
            ->whereRaw('TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) > ?', [self::SLA_HORAS_ALTA])
            ->orderBy('i.fecha_ocurrencia')
            ->select(
                'i.id_incidencia', 'i.titulo', 'ti.nombre as tipo',
                'z.nombre as zona', 'c.nombre as sucursal', 'e.nombre as estado',
                'i.fecha_ocurrencia',
                DB::raw('TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) as horas_transcurridas')
            )
            ->get();

        return response()->json(['datos' => $datos, 'sla_horas' => self::SLA_HORAS_ALTA]);
    }

    public function porTipo()
    {
        $datos = DB::table('tipos_incidencia as ti')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('ti.id_tipo', '=', 'i.id_tipo')->where('i.estado_aprobacion', '=', 'aprobada');
            })
            ->groupBy('ti.nombre')
            ->orderByDesc(DB::raw('COUNT(i.id_incidencia)'))
            ->select('ti.nombre as tipo', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porEstado()
    {
        $datos = DB::table('estados as e')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('e.id_estado', '=', 'i.id_estado_actual')->where('i.estado_aprobacion', '=', 'aprobada');
            })
            ->groupBy('e.nombre', 'e.color')
            ->select('e.nombre as estado', 'e.color', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porSucursal()
    {
        $datos = DB::table('ciudades as c')
            ->leftJoin('zonas as z', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('z.id_zona', '=', 'i.id_zona')->where('i.estado_aprobacion', '=', 'aprobada');
            })
            ->groupBy('c.id_ciudad', 'c.nombre', 'c.latitud_ref', 'c.longitud_ref')
            ->select(
                'c.id_ciudad as id',
                'c.nombre as sucursal',
                'c.latitud_ref as latitud',
                'c.longitud_ref as longitud',
                DB::raw('COUNT(i.id_incidencia) as total'),
                DB::raw("SUM(CASE WHEN i.estado_aprobacion='aprobada' AND e.nombre NOT IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as abiertas")
            )
            ->leftJoin('estados as e', 'e.id_estado', '=', 'i.id_estado_actual')
            ->orderBy('c.nombre')
            ->get();

        return response()->json($datos);
    }

    public function porZona()
    {
        $datos = DB::table('zonas as z')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('z.id_zona', '=', 'i.id_zona')->where('i.estado_aprobacion', '=', 'aprobada');
            })
            ->groupBy('z.nombre')
            ->orderByDesc(DB::raw('COUNT(i.id_incidencia)'))
            ->select('z.nombre as zona', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function ultimas()
    {
        $datos = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->where('i.estado_aprobacion', 'aprobada')
            ->orderByDesc('i.fecha_registro')
            ->limit(5)
            ->select('i.id_incidencia', 'i.titulo', 'ti.nombre as tipo', 'z.nombre as zona', 'e.nombre as estado', 'i.prioridad', 'i.fecha_ocurrencia')
            ->get();

        return response()->json($datos);
    }
}
