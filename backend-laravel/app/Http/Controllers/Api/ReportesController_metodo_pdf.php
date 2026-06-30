    // ──────────────────────────────────────────────────────────────
    //  GET /api/reportes/exportar-pdf
    //  Genera un PDF ejecutivo con KPIs, gráficos resumen y el
    //  detalle completo de incidencias del período filtrado.
    // ──────────────────────────────────────────────────────────────
    public function exportarPdf(Request $request)
    {
        // ── KPIs (mismo cálculo que /reportes/resumen) ──
        $base = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $base = $this->aplicarFiltros($base, $request);

        $resumen = (array) (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as criticas,
                AVG(i.tiempo_resolucion_horas)/24 as dias_promedio
            ")->first();

        // ── Resumen por categoría ──
        $catQuery = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo');
        $catQuery = $this->aplicarFiltros($catQuery, $request);
        $porCategoria = $catQuery->groupBy('ti.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('ti.nombre as categoria', DB::raw('COUNT(*) as total'))
            ->get();

        // ── Resumen por estado ──
        $estQuery = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado');
        $estQuery = $this->aplicarFiltros($estQuery, $request);
        $porEstado = $estQuery->groupBy('e.nombre')
            ->select('e.nombre as estado', DB::raw('COUNT(*) as total'))
            ->get();

        // ── Detalle completo de incidencias (con descripción) ──
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

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reportes.reporte-pdf', [
            'resumen'      => $resumen,
            'porCategoria' => $porCategoria,
            'porEstado'    => $porEstado,
            'incidencias'  => $incidencias,
            'desde'        => $request->query('desde'),
            'hasta'        => $request->query('hasta'),
            'generadoEn'   => now()->format('d/m/Y H:i'),
            'generadoPor'  => $usuario ? $usuario->nombre_completo : 'Sistema',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-geoincidencias-' . now()->format('Y-m-d') . '.pdf');
    }
