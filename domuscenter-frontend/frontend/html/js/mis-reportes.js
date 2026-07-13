// js/mis-reportes.js
exigirSesion();

function mostrarAlerta(msg, tipo = 'success') {
  const el = document.getElementById('alerta');
  if (!el) return;
  el.innerHTML = `
    <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  el.style.display = 'block';
  setTimeout(() => { el.innerHTML = ''; el.style.display = 'none'; }, 4000);
}

let todosReportes = [];
let filtroActivo  = '';

const ESTADO_CLASS = {
  'Pendiente':  'estado-pendiente',
  'En proceso': 'estado-proceso',
  'Resuelto':   'estado-resuelto',
  'Cerrado':    'estado-cerrado',
  'Rechazado':  'estado-rechazado',
};
const ESTADO_ICON = {
  'Pendiente':  '⏳',
  'En proceso': '🔄',
  'Resuelto':   '✅',
  'Cerrado':    '🔒',
  'Rechazado':  '❌',
};

async function cargarMisReportes() {
  try {
    const r    = await fetchAPI(`${API}/incidencias/mis-reportes`);
    const data = await r.json();
    todosReportes = data.datos || [];
    actualizarResumen();
    renderReportes();
  } catch(e) {
    document.getElementById('listaReportes').innerHTML = `
      <div class="text-center py-5 text-danger">
        <i class="bi bi-exclamation-triangle" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
        Error al cargar tus reportes
      </div>`;
  }
}

function actualizarResumen() {
  document.getElementById('totalReportes').textContent    = todosReportes.length;
  document.getElementById('pendientesCount').textContent  = todosReportes.filter(i => i.estado === 'Pendiente').length;
  document.getElementById('procesoCount').textContent     = todosReportes.filter(i => i.estado === 'En proceso').length;
  document.getElementById('resueltosCount').textContent   = todosReportes.filter(i => i.estado === 'Resuelto').length;
}

function filtrarPor(estado) {
  filtroActivo = estado;
  ['filtroTodos','filtroPendiente','filtroProceso','filtroResuelto','filtroRechazado'].forEach(id => {
    document.getElementById(id).className = 'btn btn-sm btn-outline-secondary px-3';
  });
  const mapa = {
    '':           'filtroTodos',
    'Pendiente':  'filtroPendiente',
    'En proceso': 'filtroProceso',
    'Resuelto':   'filtroResuelto',
    'Rechazado':  'filtroRechazado',
  };
  if (mapa[estado]) document.getElementById(mapa[estado]).className = 'btn btn-sm btn-danger px-3';
  renderReportes();
}

function renderReportes() {
  const lista     = document.getElementById('listaReportes');
  const filtrados = filtroActivo
    ? todosReportes.filter(i => i.estado === filtroActivo)
    : todosReportes;

  if (!filtrados.length) {
    lista.innerHTML = `
      <div class="text-center py-5 text-secondary">
        <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;"></i>
        <div>${filtroActivo ? `No tienes reportes en estado "${filtroActivo}"` : 'Aún no has registrado ninguna incidencia'}</div>
        <a href="registrar.html" class="btn btn-danger btn-sm mt-3">
          <i class="bi bi-plus-circle me-2"></i>Registrar incidencia
        </a>
      </div>`;
    return;
  }

  lista.innerHTML = filtrados.map(tarjeta).join('');
}

function tarjeta(i) {
  const estadoClass = ESTADO_CLASS[i.estado] || 'estado-cerrado';
  const estadoIcon  = ESTADO_ICON[i.estado]  || '📋';
  const fecha       = i.fecha_registro
    ? new Date(i.fecha_registro).toLocaleDateString('es-EC')
    : '—';
  const prioColor   = { Alta:'#ef4444', Media:'#eab308', Baja:'#22c55e' }[i.prioridad] || '#94a3b8';

  // Etiqueta de aprobación para incidencias aún en revisión
  const aprobacionBadge = i.estado_aprobacion === 'pendiente_revision'
    ? `<span style="background:rgba(245,158,11,.15);color:#d97706;padding:3px 8px;border-radius:12px;font-size:.73rem;border:1px solid rgba(245,158,11,.3);">
         <i class="bi bi-clock me-1"></i>En revisión
       </span>`
    : '';

  return `
  <div class="card-reporte p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div style="flex:1;min-width:0;">
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
          <span class="badge-estado ${estadoClass}">${estadoIcon} ${i.estado || 'Pendiente'}</span>
          ${aprobacionBadge}
          <span style="background:${prioColor}20;color:${prioColor};border:1px solid ${prioColor}40;padding:3px 8px;border-radius:12px;font-size:.75rem;">
            ${i.prioridad || 'Media'}
          </span>
          <span class="text-secondary" style="font-size:.78rem;">#${i.id_incidencia}</span>
        </div>
        <h6 class="fw-semibold mb-1" style="color:#0b2340;">${i.titulo}</h6>
        <div class="text-secondary" style="font-size:.82rem;">
          ${i.tipo  ? `<span><i class="bi bi-tag me-1"></i>${i.tipo}</span> · ` : ''}
          ${i.zona  ? `<span><i class="bi bi-geo-alt me-1"></i>${i.zona}</span> · ` : ''}
          <span><i class="bi bi-calendar me-1"></i>${fecha}</span>
        </div>
        ${i.descripcion
          ? `<p class="mt-2 mb-0 text-secondary" style="font-size:.83rem;line-height:1.5;">
               ${i.descripcion.substring(0, 120)}${i.descripcion.length > 120 ? '…' : ''}
             </p>`
          : ''}
      </div>
      <div class="text-end">
        <div style="font-size:.75rem;color:#64748b;">Reportado el</div>
        <div style="font-size:.85rem;color:#0b2340;">${fecha}</div>
      </div>
    </div>
    <div class="mt-3 pt-3" style="border-top:1px solid #e2e8f0;">
      <div class="d-flex align-items-center flex-wrap gap-1" style="font-size:.75rem;">
        ${timelineEstado(i.estado)}
      </div>
    </div>
  </div>`;
}

function timelineEstado(estadoActual) {
  const pasos = ['Pendiente', 'En proceso', 'Resuelto'];
  if (estadoActual === 'Rechazado') {
    return `<span style="color:#ef4444;"><i class="bi bi-x-circle-fill me-1"></i>Rechazado por el administrador</span>`;
  }
  const idx = pasos.indexOf(estadoActual);
  return pasos.map((paso, i) => {
    const ok    = i <= idx;
    const color = ok ? '#16a34a' : '#94a3b8';
    const w     = (i === idx) ? '700' : '400';
    return `
      <div class="d-flex align-items-center">
        <span style="color:${color};font-weight:${w};">${ok ? '●' : '○'} ${paso}</span>
        ${i < pasos.length - 1 ? `<span style="color:#94a3b8;margin:0 8px;">──</span>` : ''}
      </div>`;
  }).join('');
}

// ── Init ──
inicializarBarraUsuario();
cargarMisReportes();

async function exportarMisReportesPdf() {
  try {
    const r = await fetchAPI(`${API}/incidencias/mis-reportes/pdf`);
    if (!r.ok) throw new Error();
    const blob = await r.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `mis-reportes-${new Date().toISOString().slice(0,10)}.pdf`; a.click();
  } catch (e) {
    mostrarAlerta('No se pudo generar el PDF de tu historial.', 'danger');
  }
}
