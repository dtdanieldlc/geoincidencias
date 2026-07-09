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
            ->select('c.nombre as sucursal', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas"))
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
    //  Genera un PDF ejecutivo con KPIs y TODAS las consultas de
    //  análisis (categoría, estado, prioridad, sucursal, responsable,
    //  tendencia mensual), más el detalle completo del período filtrado.
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

        $prioQuery = DB::table('incidencias as i');
        $prioQuery = $this->aplicarFiltros($prioQuery, $request);
        $porPrioridad = $prioQuery->groupBy('i.prioridad')
            ->orderByRaw("FIELD(i.prioridad,'Alta','Media','Baja')")
            ->select('i.prioridad', DB::raw('COUNT(*) as total'))
            ->get();

        $sucQuery = DB::table('incidencias as i')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad');
        $sucQuery = $this->aplicarFiltros($sucQuery, $request);
        $porSucursal = $sucQuery->groupBy('c.id_ciudad', 'c.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('c.nombre as sucursal', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas"))
            ->get();

        $tendQuery = DB::table('incidencias as i');
        $tendQuery = $this->aplicarFiltros($tendQuery, $request);
        $tendencia = $tendQuery->groupBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m')"))
            ->select(DB::raw("DATE_FORMAT(i.fecha_ocurrencia, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'))
            ->get();

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
            'resumen'        => $resumen,
            'porCategoria'   => $porCategoria,
            'porEstado'      => $porEstado,
            'porPrioridad'   => $porPrioridad,
            'porSucursal'    => $porSucursal,
            'porResponsable' => $porResponsable,
            'tendencia'      => $tendencia,
            'incidencias'    => $incidencias,
            'desde'          => $request->query('desde'),
            'hasta'          => $request->query('hasta'),
            'generadoEn'     => now()->format('d/m/Y H:i'),
            'generadoPor'    => $usuario ? trim($usuario->nombre.' '.($usuario->apellido ?? '')) : 'Sistema',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-geoincidencias-' . now()->format('Y-m-d') . '.pdf');
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/reportes/exportar-csv
    //  CSV multi-sección: resumen general + todas las consultas de
    //  análisis (categoría, estado, prioridad, sucursal, responsable,
    //  tendencia mensual) + el detalle completo del período filtrado.
    //  Pensado para abrir en Excel y navegar entre secciones, no solo
    //  como un volcado plano de filas.
    // ──────────────────────────────────────────────────────────────
    public function exportarCsv(Request $request)
    {
        $usuario = $request->user();

        $base = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $base = $this->aplicarFiltros($base, $request);
        $resumen = (array) (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas,
                AVG(i.tiempo_resolucion_horas)/24 as dias_promedio
            ")->first();
        $total = $resumen['total'] ?? 0;
        $tasaResolucion = $total > 0 ? round((($resumen['resueltas'] ?? 0) / $total) * 100) : 0;

        $catQuery = DB::table('incidencias as i')->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo');
        $porCategoria = $this->aplicarFiltros($catQuery, $request)
            ->groupBy('ti.nombre')->orderByDesc(DB::raw('COUNT(*)'))
            ->select('ti.nombre as categoria', DB::raw('COUNT(*) as total'))->get();

        $estQuery = DB::table('incidencias as i')->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
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
            ")->get();

        $detalleQuery = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->leftJoin('subtipos_incidencia as st', 'i.id_subtipo', '=', 'st.id_subtipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('usuarios as uc', 'i.id_usuario_creador', '=', 'uc.id_usuario')
            ->select([
                'i.id_incidencia', 'i.titulo', 'i.descripcion', 'i.prioridad',
                'ti.nombre as tipo', 'st.nombre as subtipo', 'e.nombre as estado',
                'c.nombre as sucursal', 'z.nombre as zona',
                'i.fecha_ocurrencia', 'i.fecha_resolucion', 'i.tiempo_resolucion_horas',
                'i.reportante_nombre',
                DB::raw("CONCAT(uc.nombre,' ',IFNULL(uc.apellido,'')) as creado_por"),
            ]);
        $filas = $this->aplicarFiltros($detalleQuery, $request)->orderBy('i.fecha_ocurrencia', 'desc')->get();

        $callback = function () use (
            $resumen, $total, $tasaResolucion, $porCategoria, $porEstado,
            $porPrioridad, $porSucursal, $tendencia, $porResponsable, $filas,
            $request, $usuario
        ) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para acentos en Excel

            fputcsv($out, ['REPORTE GEOINCIDENCIAS']);
            fputcsv($out, ['Período', ($request->query('desde') ?: '—') . ' al ' . ($request->query('hasta') ?: '—')]);
            fputcsv($out, ['Generado', now()->format('d/m/Y H:i')]);
            fputcsv($out, ['Por', $usuario ? trim($usuario->nombre.' '.($usuario->apellido ?? '')) : 'Sistema']);
            fputcsv($out, []);

            fputcsv($out, ['RESUMEN GENERAL']);
            fputcsv($out, ['Incidencias en período', $total]);
            fputcsv($out, ['Resueltas', $resumen['resueltas'] ?? 0]);
            fputcsv($out, ['Tasa de resolución', $tasaResolucion . '%']);
            fputcsv($out, ['Días promedio resolución', $resumen['dias_promedio'] ? number_format($resumen['dias_promedio'], 1) : '—']);
            fputcsv($out, ['Incidencias críticas (Alta)', $resumen['criticas'] ?? 0]);
            fputcsv($out, []);

            fputcsv($out, ['POR CATEGORÍA']);
            fputcsv($out, ['Categoría', 'Total']);
            foreach ($porCategoria as $c) fputcsv($out, [$c->categoria, $c->total]);
            fputcsv($out, []);

            fputcsv($out, ['POR ESTADO']);
            fputcsv($out, ['Estado', 'Total']);
            foreach ($porEstado as $e) fputcsv($out, [$e->estado, $e->total]);
            fputcsv($out, []);

            fputcsv($out, ['POR PRIORIDAD']);
            fputcsv($out, ['Prioridad', 'Total']);
            foreach ($porPrioridad as $p) fputcsv($out, [$p->prioridad, $p->total]);
            fputcsv($out, []);

            fputcsv($out, ['POR SUCURSAL']);
            fputcsv($out, ['Sucursal', 'Total', 'Críticas (Alta)']);
            foreach ($porSucursal as $s) fputcsv($out, [$s->sucursal, $s->total, $s->criticas]);
            fputcsv($out, []);

            fputcsv($out, ['TENDENCIA MENSUAL']);
            fputcsv($out, ['Mes', 'Incidencias registradas']);
            foreach ($tendencia as $t) fputcsv($out, [$t->mes, $t->total]);
            fputcsv($out, []);

            fputcsv($out, ['POR RESPONSABLE']);
            fputcsv($out, ['Responsable', 'Asignadas', 'Resueltas', 'En proceso', 'Tasa resolución']);
            foreach ($porResponsable as $r) {
                $tasa = $r->asignadas > 0 ? round(($r->resueltas / $r->asignadas) * 100) : 0;
                fputcsv($out, [$r->responsable, $r->asignadas, $r->resueltas, $r->en_proceso, $tasa . '%']);
            }
            fputcsv($out, []);

            fputcsv($out, ['DETALLE DE INCIDENCIAS (' . count($filas) . ')']);
            fputcsv($out, ['ID','Título','Descripción','Prioridad','Tipo','Subtipo','Estado','Sucursal','Zona','Fecha ocurrencia','Fecha resolución','Horas resolución','Reportante','Creado por']);
            foreach ($filas as $f) {
                fputcsv($out, [
                    $f->id_incidencia, $f->titulo, $f->descripcion, $f->prioridad,
                    $f->tipo, $f->subtipo, $f->estado, $f->sucursal, $f->zona,
                    $f->fecha_ocurrencia, $f->fecha_resolucion, $f->tiempo_resolucion_horas,
                    $f->reportante_nombre, trim($f->creado_por ?? ''),
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'reporte-geoincidencias-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}