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
        if ($sucursal = $request->query('sucursal')) {
            $query->whereIn('i.id_zona', function ($q) use ($sucursal) {
                $q->select('id_zona')->from('zonas')->where('id_ciudad', $sucursal);
            });
        }
        return $query;
    }

    public function porSucursal(Request $request)
    {
        $query = DB::table('incidencias as i')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad');
        $query = $this->aplicarFiltros($query, $request);

        $datos = $query->groupBy('c.id_ciudad', 'c.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(
                'c.nombre as sucursal',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as alta"),
                DB::raw("SUM(CASE WHEN i.prioridad='Media' THEN 1 ELSE 0 END) as media"),
                DB::raw("SUM(CASE WHEN i.prioridad='Baja' THEN 1 ELSE 0 END) as baja"),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas")
            )
            ->get();

        return response()->json($datos);
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
            ->select(
                'ti.nombre as categoria',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as alta"),
                DB::raw("SUM(CASE WHEN i.prioridad='Media' THEN 1 ELSE 0 END) as media"),
                DB::raw("SUM(CASE WHEN i.prioridad='Baja' THEN 1 ELSE 0 END) as baja")
            )
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
    //  GET /api/reportes/exportar-pdf-resumen
    //  PDF #1: "Resumen Ejecutivo" — KPIs y TODAS las consultas de
    //  análisis (categoría, estado, prioridad, sucursal, tendencia
    //  mensual, responsable). Sin el listado fila-por-fila: es el
    //  documento para gerencia/reunión, no un volcado de datos.
    // ──────────────────────────────────────────────────────────────
    public function exportarPdfResumen(Request $request)
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
        $porCategoria = $this->aplicarFiltros($catQuery, $request)
            ->groupBy('ti.nombre')->orderByDesc(DB::raw('COUNT(*)'))
            ->select('ti.nombre as categoria', DB::raw('COUNT(*) as total'))->get();

        $estQuery = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $porEstado = $this->aplicarFiltros($estQuery, $request)
            ->groupBy('e.nombre')->select('e.nombre as estado', DB::raw('COUNT(*) as total'))->get();

        $prioQuery = DB::table('incidencias as i');
        $porPrioridad = $this->aplicarFiltros($prioQuery, $request)
            ->groupBy('i.prioridad')->orderByRaw("FIELD(i.prioridad,'Alta','Media','Baja')")
            ->select('i.prioridad', DB::raw('COUNT(*) as total'))->get();

        $sucQuery = DB::table('incidencias as i')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad');
        $porSucursal = $this->aplicarFiltros($sucQuery, $request)
            ->groupBy('c.id_ciudad', 'c.nombre')->orderByDesc(DB::raw('COUNT(*)'))
            ->select('c.nombre as sucursal', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas"))->get();

        $tendQuery = DB::table('incidencias as i');
        $tendencia = $this->aplicarFiltros($tendQuery, $request)
            ->groupBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->select(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'))->get();

        $porResponsable = DB::table('incidencia_asignaciones as ia')
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

        $usuario = $request->user();
        $total   = $resumen['total'] ?? 0;
        $tasaResolucion = $total > 0 ? round((($resumen['resueltas'] ?? 0) / $total) * 100) : 0;

        $pdf = Pdf::loadView('reportes.pdf-resumen', [
            'resumen'         => $resumen,
            'tasaResolucion'  => $tasaResolucion,
            'porCategoria'    => $porCategoria,
            'porEstado'       => $porEstado,
            'porPrioridad'    => $porPrioridad,
            'porSucursal'     => $porSucursal,
            'porResponsable'  => $porResponsable,
            'tendencia'       => $tendencia,
            'desde'           => $request->query('desde'),
            'hasta'           => $request->query('hasta'),
            'generadoEn'      => now()->format('d/m/Y H:i'),
            'generadoPor'     => $usuario ? trim($usuario->nombre.' '.($usuario->apellido ?? '')) : 'Sistema',
        ])->setPaper('a4', 'portrait');

        return $pdf->download('resumen-ejecutivo-geoincidencias-' . now()->format('Y-m-d') . '.pdf');
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/reportes/exportar-pdf-detalle
    //  PDF #2: "Detalle de Incidencias" — el listado completo, cada
    //  incidencia con todos sus campos. Documento aparte, no mezclado
    //  con el resumen ejecutivo.
    // ──────────────────────────────────────────────────────────────
    public function exportarPdfDetalle(Request $request)
    {
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

        $pdf = Pdf::loadView('reportes.pdf-detalle', [
            'incidencias' => $incidencias,
            'desde'       => $request->query('desde'),
            'hasta'       => $request->query('hasta'),
            'generadoEn'  => now()->format('d/m/Y H:i'),
            'generadoPor' => $usuario ? trim($usuario->nombre.' '.($usuario->apellido ?? '')) : 'Sistema',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('detalle-incidencias-geoincidencias-' . now()->format('Y-m-d') . '.pdf');
    }


    /**
     * Incidencias del área del encargado (o todas si admin/superadmin),
     * filtrables por rango de fechas. Por defecto últimos 30 días.
     */
    public function misResoluciones(Request $request)
    {
        $usuario = $request->user();
        $desde = $request->query('desde') ?: now()->subDays(30)->toDateString();
        $hasta = $request->query('hasta') ?: now()->toDateString();

        $q = DB::table('incidencias as i')
            ->leftJoin('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->leftJoin('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->leftJoin('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->leftJoin('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('departamentos as d', 'i.id_departamento', '=', 'd.id_departamento')
            ->whereDate('i.fecha_ocurrencia', '>=', $desde)
            ->whereDate('i.fecha_ocurrencia', '<=', $hasta)
            ->whereIn('e.nombre', ['Resuelto', 'Cerrado', 'En proceso', 'Pendiente']);

        if ($usuario->rol === 'encargado') {
            $ids = $usuario->idsDepartamentosEncargados();
            if (empty($ids)) {
                return response()->json(['datos' => [], 'total' => 0, 'desde' => $desde, 'hasta' => $hasta]);
            }
            $q->whereIn('i.id_departamento', $ids);
        }

        $datos = $q->orderByDesc('i.fecha_ocurrencia')
            ->select([
                'i.id_incidencia', 'i.titulo', 'i.prioridad',
                'e.nombre as estado', 'ti.nombre as tipo',
                'c.nombre as sucursal', 'z.nombre as zona',
                'd.nombre as departamento',
                'i.fecha_ocurrencia', 'i.fecha_resolucion',
                'i.reportante_nombre',
            ])
            ->get();

        $resueltas = $datos->whereIn('estado', ['Resuelto', 'Cerrado'])->count();

        return response()->json([
            'datos' => $datos,
            'total' => $datos->count(),
            'resueltas' => $resueltas,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function misResolucionesPdf(Request $request)
    {
        $usuario = $request->user();
        $desde = $request->query('desde') ?: now()->subDays(30)->toDateString();
        $hasta = $request->query('hasta') ?: now()->toDateString();

        $q = DB::table('incidencias as i')
            ->leftJoin('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->leftJoin('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->leftJoin('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->leftJoin('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('departamentos as d', 'i.id_departamento', '=', 'd.id_departamento')
            ->whereDate('i.fecha_ocurrencia', '>=', $desde)
            ->whereDate('i.fecha_ocurrencia', '<=', $hasta);

        if ($usuario->rol === 'encargado') {
            $ids = $usuario->idsDepartamentosEncargados();
            if (empty($ids)) {
                $ids = [-1];
            }
            $q->whereIn('i.id_departamento', $ids);
        }

        $incidencias = $q->orderByDesc('i.fecha_ocurrencia')
            ->select([
                'i.id_incidencia', 'i.titulo', 'i.prioridad',
                'e.nombre as estado', 'ti.nombre as tipo',
                'c.nombre as sucursal', 'z.nombre as zona',
                'd.nombre as departamento',
                'i.fecha_ocurrencia', 'i.fecha_resolucion',
                'i.reportante_nombre',
            ])
            ->get();

        $nombre = trim(($usuario->nombre ?? '') . ' ' . ($usuario->apellido ?? ''));
        $html = view('reportes.pdf-mis-resoluciones', [
            'incidencias' => $incidencias,
            'desde' => $desde,
            'hasta' => $hasta,
            'generadoEn' => now()->format('d/m/Y H:i'),
            'generadoPor' => $nombre,
            'rol' => $usuario->rol,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download('resoluciones-' . now()->format('Y-m-d') . '.pdf');
    }

}
