// frontend/js/admin.js
exigirAdmin();

let rechazarIncidenciaId = null;
let rechazarApoyoId      = null;

function mostrarAlerta(msg, tipo='success') {
  const el = document.getElementById('alerta');
  el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show shadow-lg border-0" role="alert">
    <i class="bi bi-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 4000);
}

function cambiarTab(tab) {
  document.getElementById('panelIncidencias').style.display = tab==='incidencias' ? '' : 'none';
  document.getElementById('panelApoyos').style.display       = tab==='apoyos' ? '' : 'none';
  document.getElementById('tabIncidenciasBtn').classList.toggle('active', tab==='incidencias');
  document.getElementById('tabApoyosBtn').classList.toggle('active', tab==='apoyos');
}

const COLOR_PRIO = { 'Alta':'#ef4444','Media':'#eab308','Baja':'#22c55e' };
function badgePrio(p) {
  const c = COLOR_PRIO[p] || '#94a3b8';
  return `<span class="badge" style="background:${c}20;color:${c};border:1px solid ${c}40;padding:4px 8px;font-size:.72rem;">${p}</span>`;
}

// ── Cargar incidencias pendientes de revisión ──
async function cargarPendientesIncidencias() {
  try {
    const r = await fetchAPI(`${API}/incidencias/pendientes-aprobacion`);
    const datos = await r.json();
    document.getElementById('cntPendIncidencias').textContent = datos.length;

    const html = datos.map(inc => `
      <tr style="border-color:#21262d;">
        <td class="border-secondary py-3">
          <div class="fw-semibold small">${inc.titulo}</div>
          <div class="text-secondary" style="font-size:.78rem;">${inc.descripcion ? inc.descripcion.substring(0,70)+'…' : ''}</div>
        </td>
        <td class="border-secondary small text-secondary">${inc.tipo}${inc.subtipo ? ' / '+inc.subtipo : ''}</td>
        <td class="border-secondary small text-secondary">${inc.zona}</td>
        <td class="border-secondary">${badgePrio(inc.prioridad)}</td>
        <td class="border-secondary small text-secondary">${inc.creado_por || inc.reportante_nombre || '—'}</td>
        <td class="border-secondary small text-secondary">${new Date(inc.fecha_ocurrencia).toLocaleDateString('es-EC')}</td>
        <td class="border-secondary">
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-success" title="Aprobar" onclick="aprobarIncidencia(${inc.id_incidencia})"><i class="bi bi-check-lg"></i></button>
            <button class="btn btn-sm btn-outline-danger" title="Rechazar" onclick="abrirRechazarIncidencia(${inc.id_incidencia})"><i class="bi bi-x-lg"></i></button>
          </div>
        </td>
      </tr>`).join('');

    document.getElementById('tbodyPendIncidencias').innerHTML =
      html || '<tr><td colspan="7" class="text-center text-secondary py-4">No hay incidencias pendientes de revisión 🎉</td></tr>';
  } catch(e) {
    document.getElementById('tbodyPendIncidencias').innerHTML =
      '<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar</td></tr>';
  }
}

async function aprobarIncidencia(id) {
  try {
    const r = await fetchAPI(`${API}/incidencias/${id}/aprobar`, { method:'PUT' });
    const data = await r.json();
    if (data.ok) { mostrarAlerta('Incidencia aprobada y ya es visible.', 'success'); cargarPendientesIncidencias(); }
    else mostrarAlerta(data.mensaje, 'danger');
  } catch(e) { mostrarAlerta(e.message, 'danger'); }
}

function abrirRechazarIncidencia(id) {
  rechazarIncidenciaId = id;
  document.getElementById('rechazarIncMotivo').value = '';
  document.activeElement?.blur(); 
  new bootstrap.Modal(document.getElementById('modalRechazarInc')).show();
}

async function confirmarRechazoIncidencia() {
  const motivo = document.getElementById('rechazarIncMotivo').value.trim();
  try {
    const r = await fetchAPI(`${API}/incidencias/${rechazarIncidenciaId}/rechazar`, {
      method:'PUT', body: JSON.stringify({ motivo })
    });
    const data = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('modalRechazarInc')).hide();
    if (data.ok) { mostrarAlerta('Incidencia rechazada.', 'success'); cargarPendientesIncidencias(); }
    else mostrarAlerta(data.mensaje, 'danger');
  } catch(e) { mostrarAlerta(e.message, 'danger'); }
}

// ── Cargar apoyos pendientes de aprobación (incentivos) ──
async function cargarPendientesApoyos() {
  try {
    const r = await fetchAPI(`${API}/apoyos/pendientes`);
    const datos = await r.json();
    document.getElementById('cntPendApoyos').textContent = datos.length;

    const html = datos.map(a => `
      <tr style="border-color:#21262d;">
        <td class="border-secondary small fw-semibold">${a.incidencia_titulo}</td>
        <td class="border-secondary small text-secondary">${a.usuario}</td>
        <td class="border-secondary small text-secondary">${a.comentario_usuario || '—'}</td>
        <td class="border-secondary"><span class="badge bg-success bg-opacity-25 text-success">$${parseFloat(a.monto_incentivo).toFixed(2)}</span></td>
        <td class="border-secondary small text-secondary">${new Date(a.fecha_apoyo).toLocaleString('es-EC')}</td>
        <td class="border-secondary">
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-success" title="Aprobar y pagar" onclick="aprobarApoyo(${a.id_apoyo})"><i class="bi bi-check-lg"></i></button>
            <button class="btn btn-sm btn-outline-danger" title="Rechazar" onclick="abrirRechazarApoyo(${a.id_apoyo})"><i class="bi bi-x-lg"></i></button>
          </div>
        </td>
      </tr>`).join('');

    document.getElementById('tbodyPendApoyos').innerHTML =
      html || '<tr><td colspan="6" class="text-center text-secondary py-4">No hay incentivos pendientes de aprobación 🎉</td></tr>';
  } catch(e) {
    document.getElementById('tbodyPendApoyos').innerHTML =
      '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar</td></tr>';
  }
}

async function aprobarApoyo(id) {
  try {
    const r = await fetchAPI(`${API}/apoyos/${id}/aprobar`, { method:'PUT', body: JSON.stringify({}) });
    const data = await r.json();
    if (data.ok) { mostrarAlerta(data.mensaje, 'success'); cargarPendientesApoyos(); }
    else mostrarAlerta(data.mensaje, 'danger');
  } catch(e) { mostrarAlerta(e.message, 'danger'); }
}

function abrirRechazarApoyo(id) {
  rechazarApoyoId = id;
  document.getElementById('rechazarApoyoComentario').value = '';
  document.activeElement?.blur(); 
  new bootstrap.Modal(document.getElementById('modalRechazarApoyo')).show();
}

async function confirmarRechazoApoyo() {
  const comentario_admin = document.getElementById('rechazarApoyoComentario').value.trim();
  try {
    const r = await fetchAPI(`${API}/apoyos/${rechazarApoyoId}/rechazar`, {
      method:'PUT', body: JSON.stringify({ comentario_admin })
    });
    const data = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('modalRechazarApoyo')).hide();
    if (data.ok) { mostrarAlerta('Apoyo rechazado.', 'success'); cargarPendientesApoyos(); }
    else mostrarAlerta(data.mensaje, 'danger');
  } catch(e) { mostrarAlerta(e.message, 'danger'); }
}

// ── Init ──
inicializarBarraUsuario();
cargarPendientesIncidencias();
cargarPendientesApoyos();
