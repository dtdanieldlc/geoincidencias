<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 32px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 10px; }

    /* ── Header ── */
    .header { border-bottom: 3px solid #b91c1c; padding-bottom: 10px; margin-bottom: 16px; }
    .header table { width: 100%; }
    .header .titulo { font-size: 19px; font-weight: bold; color: #b91c1c; }
    .header .subtitulo { font-size: 10px; color: #555; margin-top: 2px; }
    .header .meta { text-align: right; font-size: 9px; color: #555; }

    /* ── KPIs ── */
    .kpis { width: 100%; margin-bottom: 18px; }
    .kpis td { width: 25%; padding: 10px 8px; border: 1px solid #ddd; text-align: center; background: #fafafa; }
    .kpis .num { font-size: 18px; font-weight: bold; color: #b91c1c; display: block; }
    .kpis .lbl { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: .5px; }

    /* ── Section title ── */
    .sec-title { font-size: 12px; font-weight: bold; color: #b91c1c; margin: 16px 0 6px;
                 border-left: 4px solid #b91c1c; padding-left: 8px; }

    /* ── Resumen tables (estado / categoria) ── */
    .resumen-cols { width: 100%; margin-bottom: 6px; }
    .resumen-cols td { vertical-align: top; width: 50%; padding-right: 10px; }
    .mini-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    .mini-table th { background: #b91c1c; color: #fff; padding: 5px 6px; text-align: left; font-size: 8.5px; }
    .mini-table td { padding: 4px 6px; border-bottom: 1px solid #eee; }

    /* ── Main incidents table ── */
    .tabla { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .tabla th { background: #1f2937; color: #fff; padding: 6px 5px; font-size: 8.5px; text-align: left; }
    .tabla td { padding: 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.3px; vertical-align: top; }
    .tabla tr:nth-child(even) { background: #f9fafb; }

    .badge { padding: 2px 6px; border-radius: 8px; font-size: 7.5px; color: #fff; font-weight: bold; }
    .prio-alta  { background: #dc2626; }
    .prio-media { background: #d97706; }
    .prio-baja  { background: #16a34a; }

    .desc { color: #555; font-size: 7.8px; }

    .footer { position: fixed; bottom: -14px; left: 0; right: 0; text-align: center;
              font-size: 7.5px; color: #999; border-top: 1px solid #ddd; padding-top: 4px; }
</style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td>
                <div class="titulo">GeoIncidencias</div>
                <div class="subtitulo">Reporte Ejecutivo de Incidencias</div>
            </td>
            <td class="meta">
                Período: {{ $desde ?? '—' }} al {{ $hasta ?? '—' }}<br>
                Generado: {{ $generadoEn }}<br>
                Por: {{ $generadoPor }}
            </td>
        </tr>
    </table>
</div>

<table class="kpis">
    <tr>
        <td><span class="num">{{ $resumen['total'] ?? 0 }}</span><span class="lbl">Incidencias en período</span></td>
        <td><span class="num">{{ $resumen['dias_promedio'] ? number_format($resumen['dias_promedio'], 1) : '—' }}</span><span class="lbl">Días promedio resolución</span></td>
        <td><span class="num">{{ ($resumen['total'] ?? 0) > 0 ? round((($resumen['resueltas'] ?? 0) / $resumen['total']) * 100) : 0 }}%</span><span class="lbl">Tasa de resolución</span></td>
        <td><span class="num">{{ $resumen['criticas'] ?? 0 }}</span><span class="lbl">Incidencias críticas</span></td>
    </tr>
</table>

<div class="sec-title">Resumen por Categoría y Estado</div>
<table class="resumen-cols">
    <tr>
        <td>
            <table class="mini-table">
                <tr><th>Categoría</th><th style="text-align:right;">Total</th></tr>
                @forelse($porCategoria as $c)
                <tr><td>{{ $c->categoria }}</td><td style="text-align:right;">{{ $c->total }}</td></tr>
                @empty
                <tr><td colspan="2">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
        <td>
            <table class="mini-table">
                <tr><th>Estado</th><th style="text-align:right;">Total</th></tr>
                @forelse($porEstado as $e)
                <tr><td>{{ $e->estado }}</td><td style="text-align:right;">{{ $e->total }}</td></tr>
                @empty
                <tr><td colspan="2">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<div class="sec-title">Resumen por Prioridad y Sucursal</div>
<table class="resumen-cols">
    <tr>
        <td>
            <table class="mini-table">
                <tr><th>Prioridad</th><th style="text-align:right;">Total</th></tr>
                @forelse($porPrioridad as $p)
                <tr><td>{{ $p->prioridad }}</td><td style="text-align:right;">{{ $p->total }}</td></tr>
                @empty
                <tr><td colspan="2">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
        <td>
            <table class="mini-table">
                <tr><th>Sucursal</th><th style="text-align:right;">Total</th><th style="text-align:right;">Críticas</th></tr>
                @forelse($porSucursal as $s)
                <tr><td>{{ $s->sucursal }}</td><td style="text-align:right;">{{ $s->total }}</td><td style="text-align:right;">{{ $s->criticas }}</td></tr>
                @empty
                <tr><td colspan="3">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<div class="sec-title">Tendencia Mensual y Por Responsable</div>
<table class="resumen-cols">
    <tr>
        <td>
            <table class="mini-table">
                <tr><th>Mes</th><th style="text-align:right;">Incidencias</th></tr>
                @forelse($tendencia as $t)
                <tr><td>{{ $t->mes }}</td><td style="text-align:right;">{{ $t->total }}</td></tr>
                @empty
                <tr><td colspan="2">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
        <td>
            <table class="mini-table">
                <tr><th>Responsable</th><th style="text-align:right;">Asignadas</th><th style="text-align:right;">Resueltas</th></tr>
                @forelse($porResponsable as $r)
                <tr><td>{{ $r->responsable }}</td><td style="text-align:right;">{{ $r->asignadas }}</td><td style="text-align:right;">{{ $r->resueltas }}</td></tr>
                @empty
                <tr><td colspan="3">Sin datos</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<div class="sec-title">Detalle de Incidencias ({{ count($incidencias) }})</div>
<table class="tabla">
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th style="width:14%;">Título</th>
            <th style="width:16%;">Descripción</th>
            <th style="width:9%;">Tipo</th>
            <th style="width:9%;">Zona</th>
            <th style="width:6%;">Prioridad</th>
            <th style="width:9%;">Estado</th>
            <th style="width:10%;">Reportado por</th>
            <th style="width:8%;">F. Ocurrencia</th>
            <th style="width:8%;">F. Resolución</th>
            <th style="width:8%;">Tiempo (h)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($incidencias as $i)
        <tr>
            <td>{{ $i->id_incidencia }}</td>
            <td><strong>{{ $i->titulo }}</strong></td>
            <td class="desc">{{ \Illuminate\Support\Str::limit($i->descripcion, 90) }}</td>
            <td>{{ $i->tipo }}{{ $i->subtipo ? ' / '.$i->subtipo : '' }}</td>
            <td>{{ $i->zona }}, {{ $i->ciudad }}</td>
            <td>
                @php $clasePrio = match($i->prioridad) { 'Alta' => 'prio-alta', 'Media' => 'prio-media', default => 'prio-baja' }; @endphp
                <span class="badge {{ $clasePrio }}">{{ $i->prioridad }}</span>
            </td>
            <td>{{ $i->estado }}</td>
            <td>{{ $i->creado_por ?: ($i->reportante_nombre ?: '—') }}</td>
            <td>{{ \Carbon\Carbon::parse($i->fecha_ocurrencia)->format('d/m/Y') }}</td>
            <td>{{ $i->fecha_resolucion ? \Carbon\Carbon::parse($i->fecha_resolucion)->format('d/m/Y') : '—' }}</td>
            <td>{{ $i->tiempo_resolucion_horas ? number_format($i->tiempo_resolucion_horas, 1) : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="11" style="text-align:center; padding:14px;">No hay incidencias en el período seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    GeoIncidencias · Reporte generado automáticamente · {{ $generadoEn }}
</div>

</body>
</html>
