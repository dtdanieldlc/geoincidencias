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

    .estado { padding: 2px 7px; border-radius: 8px; font-size: 7.5px; font-weight: bold; }
    .est-resuelto  { background: #dcfce7; color: #16a34a; }
    .est-proceso   { background: #fef3c7; color: #d97706; }
    .est-pendiente { background: #fee2e2; color: #dc2626; }
    .est-otro      { background: #e2e8f0; color: #475569; }

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
                <div class="doc-title">Mis Reportes — Historial Personal</div>
            </td>
            <td class="meta-box">
                Usuario: <strong>{{ $usuario }}</strong><br>
                Generado: <strong>{{ $generadoEn }}</strong>
            </td>
        </tr>
    </table>
</div>
<div class="teal-bar"></div>

<div class="count-pill">{{ count($incidencias) }} incidencia{{ count($incidencias) === 1 ? '' : 's' }} reportada{{ count($incidencias) === 1 ? '' : 's' }} por ti</div>

<table class="tabla">
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th style="width:16%;">Título</th>
            <th style="width:20%;">Descripción</th>
            <th style="width:10%;">Tipo</th>
            <th style="width:12%;">Zona</th>
            <th style="width:7%;">Prioridad</th>
            <th style="width:10%;">Estado</th>
            <th style="width:9%;">F. Ocurrencia</th>
            <th style="width:9%;">F. Resolución</th>
            <th style="width:7%;">Tiempo (h)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($incidencias as $i)
        <tr>
            <td>{{ $i->id_incidencia }}</td>
            <td><strong>{{ $i->titulo }}</strong></td>
            <td class="desc">{{ \Illuminate\Support\Str::limit($i->descripcion, 100) }}</td>
            <td>{{ $i->tipo }}{{ $i->subtipo ? ' / '.$i->subtipo : '' }}</td>
            <td>{{ $i->zona }}, {{ $i->ciudad }}</td>
            <td>
                @php $clasePrio = match($i->prioridad) { 'Alta' => 'prio-alta', 'Media' => 'prio-media', default => 'prio-baja' }; @endphp
                <span class="badge {{ $clasePrio }}">{{ $i->prioridad }}</span>
            </td>
            <td>
                @php
                    $claseEstado = match($i->estado) {
                        'Resuelto', 'Cerrado' => 'est-resuelto',
                        'En proceso' => 'est-proceso',
                        'Pendiente' => 'est-pendiente',
                        default => 'est-otro',
                    };
                @endphp
                <span class="estado {{ $claseEstado }}">{{ $i->estado }}</span>
            </td>
            <td>{{ \Carbon\Carbon::parse($i->fecha_ocurrencia)->format('d/m/Y') }}</td>
            <td>{{ $i->fecha_resolucion ? \Carbon\Carbon::parse($i->fecha_resolucion)->format('d/m/Y') : '—' }}</td>
            <td>{{ $i->tiempo_resolucion_horas ? number_format($i->tiempo_resolucion_horas, 1) : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center; padding:14px;">Todavía no has reportado ninguna incidencia.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    DomusCenter · GeoIncidencias · Historial Personal generado automáticamente · {{ $generadoEn }}
</div>

</body>
</html>
