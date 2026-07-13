<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 26px 30px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1a2332; font-size: 9.5px; }

    /* ── Masthead ── */
    .masthead { background: #0b2340; border-radius: 6px; padding: 14px 20px; margin-bottom: 6px; }
    .masthead table { width: 100%; }
    .brand-badge { display: inline-block; width: 10px; height: 10px; background: #14b8a6; border-radius: 2px; }
    .brand { color: #ffffff; font-size: 16px; font-weight: bold; }
    .brand-sub { color: #14b8a6; font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; font-weight: bold; }
    .doc-title { color: #ffffff; font-size: 12.5px; font-weight: bold; margin-top: 5px; }
    .meta-box { text-align: right; color: #cbd5e1; font-size: 8.5px; line-height: 1.6; }
    .meta-box strong { color: #ffffff; }
    .teal-bar { height: 4px; background: #14b8a6; border-radius: 0 0 4px 4px; margin-bottom: 14px; }

    .count-pill { display: inline-block; background: #eef4f8; color: #0b2340; border-radius: 10px;
                  padding: 2px 10px; font-size: 9px; font-weight: bold; margin-bottom: 10px; }

    /* ── Main table ── */
    .tabla { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .tabla th { background: #0b2340; color: #fff; padding: 7px 6px; font-size: 8.5px; text-align: left; }
    .tabla td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 8.3px; vertical-align: top; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }

    .badge { padding: 2px 7px; border-radius: 8px; font-size: 7.5px; color: #fff; font-weight: bold; }
    .prio-alta  { background: #dc2626; }
    .prio-media { background: #d97706; }
    .prio-baja  { background: #0d9488; }

    .desc { color: #64748b; font-size: 7.8px; }

    .footer { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center;
              font-size: 7.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
</style>
</head>
<body>

<div class="masthead">
    <table>
        <tr>
            <td>
                <span class="brand-badge"></span>
                <span class="brand">&nbsp;DomusCenter</span><br>
                <span class="brand-sub">GeoIncidencias</span>
                <div class="doc-title">Detalle de Incidencias</div>
            </td>
            <td class="meta-box">
                Período: <strong>{{ $desde ?? '—' }}</strong> al <strong>{{ $hasta ?? '—' }}</strong><br>
                Generado: <strong>{{ $generadoEn }}</strong><br>
                Por: <strong>{{ $generadoPor }}</strong>
            </td>
        </tr>
    </table>
</div>
<div class="teal-bar"></div>

<div class="count-pill">{{ count($incidencias) }} incidencia{{ count($incidencias) === 1 ? '' : 's' }} en el período</div>

<table class="tabla">
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th style="width:14%;">Título</th>
            <th style="width:17%;">Descripción</th>
            <th style="width:9%;">Tipo</th>
            <th style="width:10%;">Zona</th>
            <th style="width:6%;">Prioridad</th>
            <th style="width:9%;">Estado</th>
            <th style="width:10%;">Reportado por</th>
            <th style="width:8%;">F. Ocurrencia</th>
            <th style="width:8%;">F. Resolución</th>
            <th style="width:6%;">Tiempo (h)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($incidencias as $i)
        <tr>
            <td>{{ $i->id_incidencia }}</td>
            <td><strong>{{ $i->titulo }}</strong></td>
            <td class="desc">{{ \Illuminate\Support\Str::limit($i->descripcion, 85) }}</td>
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
    DomusCenter · GeoIncidencias · Detalle de Incidencias generado automáticamente · {{ $generadoEn }}
</div>

</body>
</html>
