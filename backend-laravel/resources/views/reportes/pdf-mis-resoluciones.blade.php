<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Resoluciones</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0b2340; }
  h1 { font-size: 18px; margin: 0 0 4px; }
  .meta { color: #64748b; margin-bottom: 14px; font-size: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #0b2340; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
  td { border-bottom: 1px solid #e2e8f0; padding: 5px 8px; font-size: 10px; }
  tr:nth-child(even) td { background: #f8fafc; }
  .badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; }
</style>
</head>
<body>
  <h1>DomusCenter — Informe de incidencias del área</h1>
  <div class="meta">
    Generado por: {{ $generadoPor }} ({{ $rol }}) · {{ $generadoEn }}<br>
    Período: {{ $desde ?: '—' }} al {{ $hasta ?: '—' }} · Total: {{ count($incidencias) }} incidencia(s)
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Título</th>
        <th>Tipo</th>
        <th>Depto</th>
        <th>Sucursal</th>
        <th>Prioridad</th>
        <th>Estado</th>
        <th>Ocurrencia</th>
        <th>Resolución</th>
        <th>Reportante</th>
      </tr>
    </thead>
    <tbody>
      @forelse($incidencias as $i)
      <tr>
        <td>{{ $i->id_incidencia }}</td>
        <td>{{ $i->titulo }}</td>
        <td>{{ $i->tipo }}</td>
        <td>{{ $i->departamento }}</td>
        <td>{{ $i->sucursal }}</td>
        <td>{{ $i->prioridad }}</td>
        <td>{{ $i->estado }}</td>
        <td>{{ $i->fecha_ocurrencia }}</td>
        <td>{{ $i->fecha_resolucion ?: '—' }}</td>
        <td>{{ $i->reportante_nombre }}</td>
      </tr>
      @empty
      <tr><td colspan="10" style="text-align:center;padding:20px;">No hay incidencias en el período</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
