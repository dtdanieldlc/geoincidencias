<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 30px 34px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1a2332; font-size: 10px; }

    .masthead { background: #0b2340; border-radius: 6px; padding: 16px 20px; margin-bottom: 6px; }
    .masthead table { width: 100%; }
    .brand-badge { display: inline-block; width: 10px; height: 10px; background: #14b8a6; border-radius: 2px; }
    .brand { color: #ffffff; font-size: 16px; font-weight: bold; }
    .brand-sub { color: #14b8a6; font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; font-weight: bold; }
    .doc-title { color: #ffffff; font-size: 13px; font-weight: bold; margin-top: 6px; }
    .meta-box { text-align: right; color: #cbd5e1; font-size: 8.5px; line-height: 1.6; }
    .meta-box strong { color: #ffffff; }
    .teal-bar { height: 4px; background: #14b8a6; border-radius: 0 0 4px 4px; margin-bottom: 18px; }

    .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 9px; color: #fff; font-weight: bold; }
    .prio-alta  { background: #dc2626; }
    .prio-media { background: #d97706; }
    .prio-baja  { background: #0d9488; }

    .titulo-inc { font-size: 15px; font-weight: bold; color: #0b2340; margin: 4px 0 2px; }

    .datos-grid { width: 100%; border-collapse: collapse; margin: 14px 0; }
    .datos-grid td { padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9.5px; }
    .datos-grid .lbl { background: #eef4f8; color: #0b2340; font-weight: bold; width: 22%; }

    .sec-title { font-size: 11px; font-weight: bold; color: #ffffff; margin: 16px 0 8px;
                 background: #0b2340; padding: 6px 10px; border-radius: 4px; }

    .desc-box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; background: #f8fafc; line-height: 1.5; }

    .comentario { border-bottom: 1px solid #eef2f6; padding: 7px 0; }
    .comentario .autor { font-weight: bold; color: #0b2340; }
    .comentario .fecha { color: #94a3b8; font-size: 8px; }

    .foto-link { display: block; color: #0d9488; font-size: 8.5px; padding: 3px 0; word-break: break-all; }

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
                <div class="doc-title">Ficha de Incidencia #{{ $inc->id_incidencia }}</div>
            </td>
            <td class="meta-box">
                Generado: <strong>{{ $generadoEn }}</strong>
            </td>
        </tr>
    </table>
</div>
<div class="teal-bar"></div>

<div class="titulo-inc">{{ $inc->titulo }}</div>
@php $clasePrio = match($inc->prioridad) { 'Alta' => 'prio-alta', 'Media' => 'prio-media', default => 'prio-baja' }; @endphp
<span class="badge {{ $clasePrio }}">Prioridad {{ $inc->prioridad }}</span>
<span class="badge" style="background:#64748b;">{{ $inc->estado ?? $inc->estado_nombre ?? '—' }}</span>

<table class="datos-grid">
    <tr>
        <td class="lbl">Tipo</td><td>{{ $inc->tipo ?? '—' }}{{ !empty($inc->subtipo) ? ' / '.$inc->subtipo : '' }}</td>
        <td class="lbl">Zona</td><td>{{ $inc->zona ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Sucursal</td><td>{{ $inc->ciudad ?? $inc->sucursal ?? '—' }}</td>
        <td class="lbl">Fecha ocurrencia</td><td>{{ \Carbon\Carbon::parse($inc->fecha_ocurrencia)->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td class="lbl">Reportado por</td><td>{{ $inc->reportante_nombre ?? '—' }}</td>
        <td class="lbl">Contacto</td><td>{{ $inc->reportante_contacto ?? '—' }}</td>
    </tr>
    @if(!empty($inc->fecha_resolucion))
    <tr>
        <td class="lbl">Fecha resolución</td><td>{{ \Carbon\Carbon::parse($inc->fecha_resolucion)->format('d/m/Y H:i') }}</td>
        <td class="lbl">Tiempo resolución</td><td>{{ $inc->tiempo_resolucion_horas ? number_format($inc->tiempo_resolucion_horas, 1).' horas' : '—' }}</td>
    </tr>
    @endif
    @if(!empty($inc->latitud) && !empty($inc->longitud))
    <tr>
        <td class="lbl">Coordenadas</td><td colspan="3">{{ $inc->latitud }}, {{ $inc->longitud }}</td>
    </tr>
    @endif
</table>

<div class="sec-title">Descripción</div>
<div class="desc-box">{{ $inc->descripcion ?: 'Sin descripción.' }}</div>

<div class="sec-title">Comentarios / Seguimiento ({{ count($comentarios) }})</div>
@forelse($comentarios as $c)
<div class="comentario">
    <span class="autor">{{ $c->usuario ? trim($c->usuario->nombre.' '.($c->usuario->apellido ?? '')) : 'Usuario' }}</span>
    &nbsp;·&nbsp;<span class="fecha">{{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y H:i') }}</span><br>
    {{ $c->comentario }}
</div>
@empty
<div style="color:#94a3b8;">Sin comentarios registrados.</div>
@endforelse

@if(count($fotos) > 0)
<div class="sec-title">Fotos de evidencia ({{ count($fotos) }})</div>
@foreach($fotos as $url)
<a class="foto-link" href="{{ $url }}">{{ $url }}</a>
@endforeach
@endif

<div class="footer">
    DomusCenter · GeoIncidencias · Ficha generada automáticamente · {{ $generadoEn }}
</div>

</body>
</html>
