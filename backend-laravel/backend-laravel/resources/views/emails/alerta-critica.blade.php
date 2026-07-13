<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family: Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:24px auto; background:#ffffff; border-radius:8px; overflow:hidden;">
    <tr>
      <td style="background:#dc2626; padding:18px 24px;">
        <span style="color:#ffffff; font-size:18px; font-weight:bold;">🚨 Incidencia crítica reportada</span><br>
        <span style="color:#fecaca; font-size:11px; letter-spacing:.5px; text-transform:uppercase;">DomusCenter · GeoIncidencias</span>
      </td>
    </tr>
    <tr>
      <td style="padding:22px 24px;">
        <h2 style="margin:0 0 6px; color:#0b2340; font-size:19px;">{{ $incidencia->titulo }}</h2>
        <p style="color:#64748b; font-size:13px; margin:0 0 16px;">
          Prioridad <strong style="color:#dc2626;">{{ $incidencia->prioridad }}</strong> ·
          Reportado {{ $incidencia->fecha_registro?->diffForHumans() ?? 'recién' }}
        </p>

        <table width="100%" cellpadding="6" style="font-size:13px; color:#1a2332; background:#f8fafc; border-radius:6px;">
          <tr><td style="color:#64748b; width:35%;">Descripción</td><td>{{ $incidencia->descripcion ?: '—' }}</td></tr>
          <tr><td style="color:#64748b;">Fecha ocurrencia</td><td>{{ \Carbon\Carbon::parse($incidencia->fecha_ocurrencia)->format('d/m/Y H:i') }}</td></tr>
          <tr><td style="color:#64748b;">Reportado por</td><td>{{ $incidencia->reportante_nombre ?: '—' }}</td></tr>
          <tr><td style="color:#64748b;">Contacto</td><td>{{ $incidencia->reportante_contacto ?: '—' }}</td></tr>
        </table>

        <p style="font-size:12px; color:#94a3b8; margin-top:18px;">
          Este correo se envía automáticamente para incidencias de prioridad Alta en categorías
          críticas (seguridad/accidentes), apenas se registran — no esperan a la revisión del panel.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
