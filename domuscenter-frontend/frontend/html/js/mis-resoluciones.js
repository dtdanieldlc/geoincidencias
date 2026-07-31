
exigirStaff();

function periodoRapido() {
  const dias = parseInt(document.getElementById('periodoRapido').value || '30', 10);
  const hasta = new Date();
  const desde = new Date();
  desde.setDate(desde.getDate() - dias);
  document.getElementById('desde').value = desde.toISOString().slice(0, 10);
  document.getElementById('hasta').value = hasta.toISOString().slice(0, 10);
  cargar();
}

async function cargar() {
  const desde = document.getElementById('desde').value;
  const hasta = document.getElementById('hasta').value;
  const qs = new URLSearchParams();
  if (desde) qs.set('desde', desde);
  if (hasta) qs.set('hasta', hasta);
  const tbody = document.getElementById('tbody');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Cargando…</td></tr>';
  try {
    const r = await fetchAPI(`${API}/reportes/mis-resoluciones?${qs}`);
    const data = await r.json();
    const rows = data.datos || [];
    document.getElementById('lblTotal').textContent = `${data.total || 0} total`;
    document.getElementById('lblResueltas').textContent = `${data.resueltas || 0} resueltas/cerradas`;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">No hay incidencias en este período</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(i => `
      <tr>
        <td>${i.id_incidencia}</td>
        <td>${i.titulo || '—'}</td>
        <td>${i.tipo || '—'}</td>
        <td>${i.departamento || '—'}</td>
        <td>${i.sucursal || '—'}</td>
        <td>${i.prioridad || '—'}</td>
        <td>${i.estado || '—'}</td>
        <td>${i.fecha_ocurrencia || '—'}</td>
        <td>${i.fecha_resolucion ? String(i.fecha_resolucion).slice(0, 10) : '—'}</td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${e.message || 'Error al cargar'}</td></tr>`;
  }
}

async function descargarPdf() {
  const desde = document.getElementById('desde').value;
  const hasta = document.getElementById('hasta').value;
  const qs = new URLSearchParams();
  if (desde) qs.set('desde', desde);
  if (hasta) qs.set('hasta', hasta);
  try {
    const r = await fetchAPI(`${API}/reportes/mis-resoluciones/pdf?${qs}`);
    if (!r.ok) throw new Error('No se pudo generar el PDF');
    const blob = await r.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `resoluciones-${desde || 'inicio'}-${hasta || 'fin'}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    alert(e.message || 'Error al descargar PDF');
  }
}

// Default: últimos 30 días
periodoRapido();
