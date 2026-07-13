<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 30px 34px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1a2332; font-size: 10px; }

    /* ── Masthead ── */
    .masthead { background: #0b2340; border-radius: 6px; padding: 16px 20px; margin-bottom: 6px; }
    .masthead table { width: 100%; }
    .brand-badge { display: inline-block; width: 10px; height: 10px; background: #14b8a6; border-radius: 2px; }
    .brand { color: #ffffff; font-size: 17px; font-weight: bold; }
    .brand-sub { color: #14b8a6; font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; font-weight: bold; }
    .doc-title { color: #ffffff; font-size: 13px; font-weight: bold; margin-top: 6px; }
    .meta-box { text-align: right; color: #cbd5e1; font-size: 8.5px; line-height: 1.6; }
    .meta-box strong { color: #ffffff; }
    .teal-bar { height: 4px; background: #14b8a6; border-radius: 0 0 4px 4px; margin-bottom: 18px; }

    /* ── KPIs ── */
    .kpis { width: 100%; margin-bottom: 20px; }
    .kpis td { width: 25%; padding: 12px 10px; border: 1px solid #e2e8f0; border-top: 3px solid #14b8a6;
               text-align: center; background: #f8fafc; }
    .kpis .num { font-size: 19px; font-weight: bold; color: #0b2340; display: block; }
    .kpis .lbl { font-size: 7.8px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .kpis .num.critico { color: #dc2626; }

    /* ── Section title ── */
    .sec-title { font-size: 11.5px; font-weight: bold; color: #ffffff; margin: 14px 0 8px;
                 background: #0b2340; padding: 6px 10px; border-radius: 4px; }
    .sec-title .dot { color: #14b8a6; }

    /* ── mini tables ── */
    .resumen-cols { width: 100%; margin-bottom: 4px; }
    .resumen-cols td { vertical-align: top; width: 50%; padding-right: 12px; }
    .mini-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    .mini-table th { background: #eef4f8; color: #0b2340; padding: 6px 8px; text-align: left; font-size: 8.5px;
                      border-bottom: 2px solid #14b8a6; }
    .mini-table td { padding: 5px 8px; border-bottom: 1px solid #eef2f6; }
    .mini-table tr:nth-child(even) td { background: #fafbfc; }

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
                <div class="doc-title">Resumen Ejecutivo de Incidencias</div>
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

<table class="kpis">
    <tr>
        <td><span class="num">{{ $resumen['total'] ?? 0 }}</span><span class="lbl">Incidencias en período</span></td>
        <td><span class="num">{{ $resumen['dias_promedio'] ? number_format($resumen['dias_promedio'], 1) : '—' }}</span><span class="lbl">Días promedio resolución</span></td>
        <td><span class="num">{{ $tasaResolucion }}%</span><span class="lbl">Tasa de resolución</span></td>
        <td><span class="num critico">{{ $resumen['criticas'] ?? 0 }}</span><span class="lbl">Incidencias críticas</span></td>
    </tr>
</table>

<div class="sec-title"><span class="dot">●</span> Por Categoría y Estado</div>
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

<div class="sec-title"><span class="dot">●</span> Por Prioridad y Sucursal</div>
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

<div class="sec-title"><span class="dot">●</span> Tendencia Mensual y Responsables</div>
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

<div class="footer">
    DomusCenter · GeoIncidencias · Resumen Ejecutivo generado automáticamente · {{ $generadoEn }}
</div>

</body>
</html>
