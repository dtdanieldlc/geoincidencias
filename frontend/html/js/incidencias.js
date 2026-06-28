// frontend/js/incidencias.js
exigirSesion();

let paginaActual = 1;
let idEliminar   = null;
const POR_PAG    = 10;
let mapaEditar, marcadorEditar;
let incentivosPorPrioridad = {};
let misApoyosSet = new Set();

const COLOR_ESTADO = {
  'Pendiente':  { bg:'rgba(239,68,68,.15)',   color:'#f87171' },
  'En proceso': { bg:'rgba(245,158,11,.15)',  color:'#fbbf24' },
  'Resuelto':   { bg:'rgba(34,197,94,.15)',   color:'#4ade80' },
  'Cerrado':    { bg:'rgba(148,163,184,.15)', color:'#94a3b8' },
};
const COLOR_PRIO = { 'Alta':'#ef4444','Media':'#eab308','Baja':'#22c55e' };

function badgeEstado(e) {
  const c = COLOR_ESTADO[e] || { bg:'rgba(148,163,184,.15)', color:'#94a3b8' };
  return `<span class="badge rounded-pill" style="background:${c.bg};color:${c.color};padding:5px 10px;font-size:.75rem;">${e}</span>`;
}
function badgePrio(p) {
  const c = COLOR_PRIO[p] || '#94a3b8';
  return `<span class="badge" style="background:${c}20;color:${c};border:1px solid ${c}40;padding:4px 8px;font-size:.72rem;">${p}</span>`;
}
function mostrarAlerta(msg, tipo='success') {
  const el = document.getElementById('alerta');
  el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show shadow-lg border-0" role="alert">
    <i class="bi bi-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 4000);
}
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
// frontend/html/js/incidencias.js
async function cargarIncidencias(pag = 1) {
  paginaActual = pag;

  const params = new URLSearchParams({
    pagina: pag,
    por_pagina: POR_PAG
  });

  // Capturar valores de los filtros
  const buscar     = document.getElementById('buscar').value.trim();
  const tipo       = document.getElementById('filtroTipo').value;
  const estado     = document.getElementById('filtroEstado').value;
  const prioridad  = document.getElementById('filtroPrioridad').value;
  const zona       = document.getElementById('filtroZona').value;
  const desde      = document.getElementById('filtroDesde').value;
  const hasta      = document.getElementById('filtroHasta').value;

  // Agregar solo los filtros que tienen valor
  if (buscar)    params.append('buscar', buscar);
  if (tipo)      params.append('tipo', tipo);
  if (estado)    params.append('estado', estado);
  if (prioridad) params.append('prioridad', prioridad);
  if (zona)      params.append('zona', zona);
  if (desde)     params.append('desde', desde);
  if (hasta)     params.append('hasta', hasta);

  console.log('🔍 Filtros enviados:', Object.fromEntries(params)); // Para depurar

  // Mostrar cargando
  document.getElementById('tbodyIncidencias').innerHTML = 
    '<tr><td colspan="9" class="text-center text-secondary py-4"><i class="bi bi-arrow-repeat me-1"></i>Cargando incidencias...</td></tr>';

  try {
    const response = await fetchAPI(`${API}/incidencias?${params.toString()}`);
    const data = await response.json();
    
    if (data.datos) {
      renderTabla(data);
    } else {
      document.getElementById('tbodyIncidencias').innerHTML = 
        '<tr><td colspan="9" class="text-center text-warning py-4">No se recibieron datos</td></tr>';
    }
  } catch (e) {
    console.error('Error al cargar incidencias:', e);
    document.getElementById('tbodyIncidencias').innerHTML = 
      '<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión con el servidor</td></tr>';
  }
}
function renderTabla({ datos, total, pagina, por_pagina }) {
  document.getElementById('infoRegistros').textContent = `Mostrando ${datos.length} de ${total} incidencias`;
  if (!datos.length) {
    document.getElementById('tbodyIncidencias').innerHTML =
      '<tr><td colspan="9" class="text-center text-secondary py-4">No se encontraron incidencias</td></tr>';
    document.getElementById('paginacion').innerHTML = '';
    return;
  }
  const esAdmin = getUsuario().rol === 'admin';
  const html = datos.map((inc,i) => {
    const monto = incentivosPorPrioridad[inc.prioridad] || 0;
    const yaApoya = misApoyosSet.has(inc.id_incidencia);
    const puedeApoyar = inc.estado !== 'Cerrado' && !yaApoya;

    return `
    <tr style="border-color:#21262d;">
      <td class="border-secondary text-secondary small">${(pagina-1)*por_pagina+i+1}</td>
      <td class="border-secondary py-3">
        <div class="fw-semibold small">${inc.titulo}</div>
        <div class="text-secondary" style="font-size:.78rem;">${inc.descripcion ? inc.descripcion.substring(0,60)+'…' : '#'+inc.id_incidencia}</div>
      </td>
      <td class="border-secondary small text-secondary">${inc.tipo}${inc.subtipo ? '<br><span style="font-size:.75rem;">'+inc.subtipo+'</span>' : ''}</td>
      <td class="border-secondary small text-secondary">${inc.zona}</td>
      <td class="border-secondary small text-secondary">${inc.creado_por || inc.reportante_nombre || '—'}</td>
      <td class="border-secondary">${badgePrio(inc.prioridad)}</td>
      <td class="border-secondary">${badgeEstado(inc.estado)}</td>
      <td class="border-secondary small text-secondary">${new Date(inc.fecha_ocurrencia).toLocaleDateString('es-EC')}</td>
      <td class="border-secondary">
        <div class="d-flex gap-1 flex-wrap">
          ${puedeApoyar ? `<button class="btn btn-sm btn-outline-danger" title="Apoyar ($${monto})" onclick="window.location.href='mis-apoyos.html'"><i class="bi bi-hand-thumbs-up"></i></button>` : ''}
          <button class="btn btn-sm btn-outline-primary" title="Editar" onclick="abrirEditar(${inc.id_incidencia})"><i class="bi bi-pencil"></i></button>
          ${esAdmin ? `<button class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="abrirEliminar(${inc.id_incidencia},'${inc.titulo.replace(/'/g,"\\'")}')"><i class="bi bi-trash"></i></button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
  document.getElementById('tbodyIncidencias').innerHTML = html;
  renderPaginacion(total, pagina, por_pagina);
}

function renderPaginacion(total, pagina, por_pagina) {
  const tp = Math.ceil(total/por_pagina);
  if (tp<=1) { document.getElementById('paginacion').innerHTML=''; return; }
  let html = `<li class="page-item ${pagina===1?'disabled':''}">
    <a class="page-link bg-dark border-secondary text-light" href="#" onclick="cargarIncidencias(${pagina-1})">«</a></li>`;
  for (let p=Math.max(1,pagina-2); p<=Math.min(tp,pagina+2); p++) {
    html += `<li class="page-item ${p===pagina?'active':''}">
      <a class="page-link ${p===pagina?'bg-danger border-danger':'bg-dark border-secondary text-light'}"
         href="#" onclick="cargarIncidencias(${p})">${p}</a></li>`;
  }
  html += `<li class="page-item ${pagina===tp?'disabled':''}">
    <a class="page-link bg-dark border-secondary text-light" href="#" onclick="cargarIncidencias(${pagina+1})">»</a></li>`;
  document.getElementById('paginacion').innerHTML = html;
}

function limpiarFiltros() {
  ['buscar','filtroTipo','filtroEstado','filtroPrioridad','filtroZona','filtroDesde','filtroHasta']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  cargarIncidencias();
}

// ── Contadores ──
async function cargarContadores() {
  try {
    const r = await fetchAPI(`${API}/dashboard/resumen`);
    const d = await r.json();
    document.getElementById('cntTotal').textContent     = d.total       || 0;
    document.getElementById('cntAbiertas').textContent  = d.pendientes  || 0;
    document.getElementById('cntEnProceso').textContent = d.en_proceso  || 0;
    document.getElementById('cntResueltas').textContent = d.resueltas   || 0;
  } catch(e) {}
}

// ── Poblar selects filtros y modal ──
async function poblarSelect(url, selectId) {
  try {
    const r = await fetchAPI(url);
    const datos = await r.json();
    const sel = document.getElementById(selectId);
    if (!sel) return;
    datos.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.id ?? d.id_tipo ?? d.id_subtipo ?? d.id_estado ?? d.id_zona ?? '';
      opt.textContent = d.nombre;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

async function cargarIncentivosYApoyos() {
  try {
    const [rInc, rApoyos] = await Promise.all([
      fetchAPI(`${API}/catalogos/incentivos`),
      fetchAPI(`${API}/apoyos/mis-apoyos`),
    ]);
    const incentivos = await rInc.json();
    incentivos.forEach(i => incentivosPorPrioridad[i.prioridad] = i.monto);
    const misApoyos = await rApoyos.json();
    misApoyosSet = new Set(misApoyos.map(a => a.id_incidencia));
  } catch(e) {}
}

// ── Abrir modal editar ──
async function abrirEditar(id) {
  try {
    const r   = await fetchAPI(`${API}/incidencias/${id}`);
    const inc = await r.json();
    document.getElementById('edit_id').value                = inc.id_incidencia;
    document.getElementById('edit_titulo').value            = inc.titulo;
    document.getElementById('edit_prioridad').value         = inc.prioridad;
    document.getElementById('edit_fecha_ocurrencia').value  = inc.fecha_ocurrencia?.split('T')[0] || '';
    document.getElementById('edit_descripcion').value       = inc.descripcion || '';
    document.getElementById('edit_latitud').value           = inc.latitud  || '';
    document.getElementById('edit_longitud').value          = inc.longitud || '';
    setSelectByText('edit_id_tipo',   inc.tipo);
    setSelectByText('edit_id_estado', inc.estado);
    setSelectByText('edit_id_zona',   inc.zona);
    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
    setTimeout(() => {
      if (!mapaEditar) {
        mapaEditar = L.map('mapaEditar').setView([inc.latitud||(-2.9001), inc.longitud||(-79.0059)], 14);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom:19 }).addTo(mapaEditar);
        mapaEditar.on('click', e => {
          document.getElementById('edit_latitud').value  = e.latlng.lat.toFixed(6);
          document.getElementById('edit_longitud').value = e.latlng.lng.toFixed(6);
          if (marcadorEditar) mapaEditar.removeLayer(marcadorEditar);
          marcadorEditar = L.marker([e.latlng.lat, e.latlng.lng]).addTo(mapaEditar);
        });
      }
      if (inc.latitud && inc.longitud) {
        if (marcadorEditar) mapaEditar.removeLayer(marcadorEditar);
        marcadorEditar = L.marker([inc.latitud, inc.longitud]).addTo(mapaEditar);
        mapaEditar.setView([inc.latitud, inc.longitud], 14);
      }
      mapaEditar.invalidateSize();
    }, 400);
  } catch(e) { mostrarAlerta('No se pudo cargar la incidencia.','danger'); }
}

function setSelectByText(selectId, texto) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  for (const opt of sel.options) {
    if (opt.textContent.trim() === texto) { sel.value=opt.value; break; }
  }
}

async function actualizarIncidencia() {
  const id = document.getElementById('edit_id').value;
  const payload = {
    titulo:           document.getElementById('edit_titulo').value.trim(),
    id_tipo:          parseInt(document.getElementById('edit_id_tipo').value),
    prioridad:        document.getElementById('edit_prioridad').value,
    id_estado_actual: parseInt(document.getElementById('edit_id_estado').value),
    id_zona:          parseInt(document.getElementById('edit_id_zona').value),
    fecha_ocurrencia: document.getElementById('edit_fecha_ocurrencia').value,
    descripcion:      document.getElementById('edit_descripcion').value.trim(),
    latitud:          parseFloat(document.getElementById('edit_latitud').value) || null,
    longitud:         parseFloat(document.getElementById('edit_longitud').value) || null,
  };
  try {
    const res  = await fetchAPI(`${API}/incidencias/${id}`, { method:'PUT', body:JSON.stringify(payload) });
    const data = await res.json();
    bootstrap.Modal.getInstance(document.getElementById('modalDetalle')).hide();
    if (res.ok && data.ok) { mostrarAlerta('Incidencia actualizada correctamente.','success'); cargarIncidencias(paginaActual); cargarContadores(); }
    else mostrarAlerta(data.mensaje||'Error al actualizar.','danger');
  } catch(e) { mostrarAlerta(e.message,'danger'); }
}

function abrirEliminar(id, titulo) {
  idEliminar = id;
  document.getElementById('elimTitulo').textContent = titulo;
  new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
async function confirmarEliminar() {
  try {
    const res  = await fetchAPI(`${API}/incidencias/${idEliminar}`, { method:'DELETE' });
    const data = await res.json();
    bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
    if (res.ok && data.ok) { mostrarAlerta('Incidencia eliminada.','success'); cargarIncidencias(paginaActual); cargarContadores(); }
    else mostrarAlerta(data.mensaje||'Error al eliminar.','danger');
  } catch(e) { mostrarAlerta(e.message,'danger'); }
  idEliminar = null;
}

// ── Init ──
async function init() {
  inicializarBarraUsuario();
  await cargarIncentivosYApoyos();
  poblarSelect(`${API}/catalogos/tipos`,   'filtroTipo');
  poblarSelect(`${API}/catalogos/estados`, 'filtroEstado');
  poblarSelect(`${API}/catalogos/zonas`,   'filtroZona');
  poblarSelect(`${API}/catalogos/tipos`,   'edit_id_tipo');
  poblarSelect(`${API}/catalogos/estados`, 'edit_id_estado');
  poblarSelect(`${API}/catalogos/zonas`,   'edit_id_zona');
  cargarContadores();
  cargarIncidencias();
}
init();
