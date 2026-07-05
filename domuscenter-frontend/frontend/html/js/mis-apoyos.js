// frontend/js/mis-apoyos.js
exigirSesion();

let incentivosPorPrioridad = {};
let apoyosYaMarcados = new Set();

function mostrarAlerta(msg, tipo='success') {
  const el = document.getElementById('alerta');
  el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show shadow-lg border-0" role="alert">
    <i class="bi bi-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 4000);
}

const COLOR_PRIO = { 'Alta':'#ef4444','Media':'#eab308','Baja':'#22c55e' };
function badgePrio(p) {
  const c = COLOR_PRIO[p] || '#94a3b8';
  return `<span class="badge" style="background:${c}20;color:${c};border:1px solid ${c}40;padding:4px 8px;font-size:.72rem;">${p}</span>`;
}
const COLOR_ESTADO_PAGO = {
  'pendiente_aprobacion': { bg:'rgba(245,158,11,.15)', color:'#d97706', label:'Pendiente' },
  'aprobado':             { bg:'rgba(34,197,94,.15)',  color:'#16a34a', label:'Aprobado' },
  'pagado':               { bg:'rgba(34,197,94,.15)',  color:'#16a34a', label:'Pagado' },
  'rechazado':            { bg:'rgba(239,68,68,.15)',  color:'#dc2626', label:'Rechazado' },
};
function badgeEstadoPago(e) {
  const c = COLOR_ESTADO_PAGO[e] || { bg:'rgba(148,163,184,.15)', color:'#94a3b8', label:e };
  return `<span class="badge rounded-pill" style="background:${c.bg};color:${c.color};padding:5px 10px;font-size:.75rem;">${c.label}</span>`;
}

// ── Cargar saldo ──
async function cargarSaldo() {
  try {
    const r = await fetchAPI(`${API}/apoyos/mi-saldo`);
    const d = await r.json();
    document.getElementById('saldoPagado').textContent      = `$${parseFloat(d.total_pagado||0).toFixed(2)}`;
    document.getElementById('saldoPendiente').textContent   = `$${parseFloat(d.total_pendiente||0).toFixed(2)}`;
    document.getElementById('apoyosCompletados').textContent= d.apoyos_completados || 0;
  } catch(e) {}
}

// ── Cargar tabla de incidencias activas para apoyar ──
async function cargarDisponibles() {
  try {
    const [rIncentivos, rInc, rMisApoyos] = await Promise.all([
      fetchAPI(`${API}/catalogos/incentivos`),
      fetchAPI(`${API}/incidencias?por_pagina=50`),
      fetchAPI(`${API}/apoyos/mis-apoyos`),
    ]);
    const incentivos = await rIncentivos.json();
    incentivos.forEach(i => incentivosPorPrioridad[i.prioridad] = i.monto);

    const misApoyos = await rMisApoyos.json();
    apoyosYaMarcados = new Set(misApoyos.map(a => a.id_incidencia));

    const { datos } = await rInc.json();
    const activas = datos.filter(i => i.estado !== 'Cerrado');

    const html = activas.map(inc => {
      const monto = incentivosPorPrioridad[inc.prioridad] || 0;
      const yaApoya = apoyosYaMarcados.has(inc.id_incidencia);
      return `
        <tr style="border-color:#e2e8f0;">
          <td class="border-secondary small fw-semibold">${inc.titulo}</td>
          <td class="border-secondary small text-secondary">${inc.zona}</td>
          <td class="border-secondary">${badgePrio(inc.prioridad)}</td>
          <td class="border-secondary"><span class="text-success fw-bold">$${parseFloat(monto).toFixed(2)}</span></td>
          <td class="border-secondary small text-secondary">${inc.estado}</td>
          <td class="border-secondary">
            ${yaApoya
              ? '<span class="badge bg-secondary bg-opacity-25 text-secondary"><i class="bi bi-check2 me-1"></i>Ya marcado</span>'
              : `<button class="btn btn-sm btn-outline-danger" onclick="abrirApoyar(${inc.id_incidencia},'${inc.titulo.replace(/'/g,"\\'")}',${monto})"><i class="bi bi-hand-thumbs-up me-1"></i>Apoyar</button>`}
          </td>
        </tr>`;
    }).join('');

    document.getElementById('tbodyDisponibles').innerHTML =
      html || '<tr><td colspan="6" class="text-center text-secondary py-4">No hay incidencias activas por el momento</td></tr>';
  } catch(e) {
    document.getElementById('tbodyDisponibles').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar</td></tr>';
  }
}

function abrirApoyar(id, titulo, monto) {
  document.getElementById('apoyarIncId').value = id;
  document.getElementById('apoyarIncTitulo').textContent = titulo;
  document.getElementById('apoyarIncMonto').textContent = `$${parseFloat(monto).toFixed(2)}`;
  document.getElementById('apoyarComentario').value = '';
  new bootstrap.Modal(document.getElementById('modalApoyar')).show();
}

async function confirmarApoyo() {
  const id_incidencia = document.getElementById('apoyarIncId').value;
  const comentario_usuario = document.getElementById('apoyarComentario').value.trim();
  try {
    const r = await fetchAPI(`${API}/apoyos`, {
      method:'POST', body: JSON.stringify({ id_incidencia, comentario_usuario })
    });
    const data = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('modalApoyar')).hide();
    if (data.ok) {
      mostrarAlerta(data.mensaje, 'success');
      cargarDisponibles(); cargarSaldo(); cargarMisApoyos();
    } else {
      mostrarAlerta(data.mensaje, 'danger');
    }
  } catch(e) { mostrarAlerta(e.message, 'danger'); }
}

// ── Historial de mis apoyos ──
async function cargarMisApoyos() {
  try {
    const r = await fetchAPI(`${API}/apoyos/mis-apoyos`);
    const datos = await r.json();
    const html = datos.map(a => `
      <tr style="border-color:#e2e8f0;">
        <td class="border-secondary small fw-semibold">${a.incidencia_titulo}</td>
        <td class="border-secondary"><span class="text-success fw-bold">$${parseFloat(a.monto_incentivo).toFixed(2)}</span></td>
        <td class="border-secondary">${badgeEstadoPago(a.estado_pago)}</td>
        <td class="border-secondary small text-secondary">${new Date(a.fecha_apoyo).toLocaleString('es-EC')}</td>
        <td class="border-secondary small text-secondary">${a.comentario_admin || '—'}</td>
      </tr>`).join('');
    document.getElementById('tbodyMisApoyos').innerHTML =
      html || '<tr><td colspan="5" class="text-center text-secondary py-4">Aún no has marcado ningún apoyo</td></tr>';
  } catch(e) {
    document.getElementById('tbodyMisApoyos').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar</td></tr>';
  }
}

// ── Init ──
inicializarBarraUsuario();
cargarSaldo();
cargarDisponibles();
cargarMisApoyos();
