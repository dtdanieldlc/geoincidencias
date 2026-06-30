<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportesController extends Controller
{
    private function aplicarFiltros($query, Request $request)
    {
        $query->where('i.estado_aprobacion', 'aprobada');
        if ($desde = $request->query('desde')) $query->whereDate('i.fecha_ocurrencia', '>=', $desde);
        if ($hasta = $request->query('hasta'))  $query->whereDate('i.fecha_ocurrencia', '<=', $hasta);
        if ($tipo = $request->query('tipo'))    $query->where('i.id_tipo', $tipo);
        if ($zona = $request->query('zona'))    $query->where('i.id_zona', $zona);
        return $query;
    }

    public function resumen(Request $request)
    {
        $base = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $base = $this->aplicarFiltros($base, $request);

        $resumen = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas,
                AVG(i.tiempo_resolucion_horas)/24 as dias_promedio
            ")->first();

        $porPrioridad = (clone $base)
            ->groupBy('i.prioridad')
            ->select('i.prioridad', DB::raw('COUNT(*) as total'))
            ->get();

        return response()->json(array_merge((array) $resumen, ['por_prioridad' => $porPrioridad]));
    }

    public function porCategoria(Request $request)
    {
        $query = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo');
        $query = $this->aplicarFiltros($query, $request);

        $datos = $query->groupBy('ti.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('ti.nombre as categoria', DB::raw('COUNT(*) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porEstado(Request $request)
    {
        $query = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $query = $this->aplicarFiltros($query, $request);

        $datos = $query->groupBy('e.nombre')
            ->select('e.nombre as estado', DB::raw('COUNT(*) as total'))
            ->get();

        return response()->json($datos);
    }

    public function tendencia(Request $request)
    {
        $query = DB::table('incidencias as i');
        $query = $this->aplicarFiltros($query, $request);

        $datos = $query->groupBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->select(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porResponsable()
    {
        $datos = DB::table('incidencia_asignaciones as ia')
            ->join('usuarios as u', 'ia.id_usuario', '=', 'u.id_usuario')
            ->join('incidencias as i', 'ia.id_incidencia', '=', 'i.id_incidencia')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->where('ia.rol_asignacion', 'responsable')
            ->groupBy('u.id_usuario', 'u.nombre', 'u.apellido')
            ->selectRaw("
                CONCAT(u.nombre,' ',IFNULL(u.apellido,'')) as responsable,
                COUNT(ia.id_incidencia) as asignadas,
                SUM(CASE WHEN e.nombre IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN e.nombre='En proceso' THEN 1 ELSE 0 END) as en_proceso
            ")
            ->get();

        return response()->json($datos);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/reportes/exportar-pdf
    //  Genera un PDF ejecutivo con KPIs, gráficos resumen y el
    //  detalle completo de incidencias del período filtrado.
    // ──────────────────────────────────────────────────────────────
    public function exportarPdf(Request $request)
    {
        $base = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $base = $this->aplicarFiltros($base, $request);

        $resumen = (array) (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas,
                AVG(i.tiempo_resolucion_horas)/24 as dias_promedio
            ")->first();

        $catQuery = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo');
        $catQuery = $this->aplicarFiltros($catQuery, $request);
        $porCategoria = $catQuery->groupBy('ti.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('ti.nombre as categoria', DB::raw('COUNT(*) as total'))
            ->get();

        $estQuery = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $estQuery = $this->aplicarFiltros($estQuery, $request);
        $porEstado = $estQuery->groupBy('e.nombre')
            ->select('e.nombre as estado', DB::raw('COUNT(*) as total'))
            ->get();

        $detalleQuery = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->leftJoin('subtipos_incidencia as st', 'i.id_subtipo', '=', 'st.id_subtipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('usuarios as uc', 'i.id_usuario_creador', '=', 'uc.id_usuario')
            ->select([
                'i.id_incidencia', 'i.titulo', 'i.descripcion', 'i.prioridad',
                'ti.nombre as tipo', 'st.nombre as subtipo',
                'e.nombre as estado',
                'z.nombre as zona', 'c.nombre as ciudad',
                'i.fecha_ocurrencia', 'i.fecha_resolucion', 'i.tiempo_resolucion_horas',
                'i.reportante_nombre',
                DB::raw("CONCAT(uc.nombre,' ',IFNULL(uc.apellido,'')) as creado_por"),
            ]);
        $detalleQuery = $this->aplicarFiltros($detalleQuery, $request);
        $incidencias = $detalleQuery->orderBy('i.fecha_ocurrencia', 'desc')->get();

        $usuario = $request->user();

        $pdf = Pdf::loadView('reportes.reporte-pdf', [
            'resumen'      => $resumen,
            'porCategoria' => $porCategoria,
            'porEstado'    => $porEstado,
            'incidencias'  => $incidencias,
            'desde'        => $request->query('desde'),
            'hasta'        => $request->query('hasta'),
            'generadoEn'   => now()->format('d/m/Y H:i'),
            'generadoPor'  => $usuario ? trim($usuario->nombre.' '.($usuario->apellido ?? '')) : 'Sistema',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-geoincidencias-' . now()->format('Y-m-d') . '.pdf');
    }
}