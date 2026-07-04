// frontend/js/historial.js
exigirAdmin();

let paginaHist = 1;
const POR_PAG_HIST = 20;

const ICONOS_ACCION = {
  'login':              'bi-box-arrow-in-right text-info',
  'registro_usuario':   'bi-person-plus text-info',
  'creo_incidencia':    'bi-plus-circle text-primary',
  'edito_incidencia':   'bi-pencil text-warning',
  'elimino_incidencia': 'bi-trash text-danger',
  'aprobo_incidencia':  'bi-check-circle text-success',
  'rechazo_incidencia': 'bi-x-circle text-danger',
  'marco_apoyo':        'bi-hand-thumbs-up text-info',
  'aprobo_apoyo':       'bi-cash-coin text-success',
  'rechazo_apoyo':      'bi-cash-coin text-danger',
};

function iconoAccion(a) {
  return ICONOS_ACCION[a] || 'bi-dot text-secondary';
}

async function poblarFiltroUsuarios() {
  try {
    const r = await fetchAPI(`${API}/catalogos/usuarios`);
    const datos = await r.json();
    const sel = document.getElementById('filtroUsuario');
    datos.forEach(u => {
      const opt = document.createElement('option');
      opt.value = u.id; opt.textContent = u.nombre;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

async function poblarFiltroAcciones() {
  try {
    const r = await fetchAPI(`${API}/historial/acciones`);
    const datos = await r.json();
    const sel = document.getElementById('filtroAccion');
    datos.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a; opt.textContent = a.replace(/_/g,' ');
      sel.appendChild(opt);
    });
  } catch(e) {}
}

async function cargarHistorial(pag=1) {
  paginaHist = pag;
  const params = new URLSearchParams({ pagina: pag, por_pagina: POR_PAG_HIST });
  const usuario = document.getElementById('filtroUsuario').value;
  const accion  = document.getElementById('filtroAccion').value;
  const desde   = document.getElementById('filtroDesde').value;
  const hasta   = document.getElementById('filtroHasta').value;
  if (usuario) params.append('usuario', usuario);
  if (accion)  params.append('accion', accion);
  if (desde)   params.append('desde', desde);
  if (hasta)   params.append('hasta', hasta);

  document.getElementById('tbodyHistorial').innerHTML =
    '<tr><td colspan="6" class="text-center text-secondary py-4"><i class="bi bi-arrow-repeat me-1"></i>Cargando…</td></tr>';

  try {
    const r = await fetchAPI(`${API}/historial?${params}`);
    const { datos, total, pagina, por_pagina } = await r.json();

    document.getElementById('infoRegistrosHist').textContent = `Mostrando ${datos.length} de ${total} registros`;

    const html = datos.map(h => `
      <tr style="border-color:#e2e8f0;">
        <td class="border-secondary small text-secondary">${new Date(h.fecha_hora).toLocaleString('es-EC')}</td>
        <td class="border-secondary small fw-semibold">${h.usuario || 'Sistema'}</td>
        <td class="border-secondary small"><i class="bi ${iconoAccion(h.accion)} me-1"></i>${h.accion.replace(/_/g,' ')}</td>
        <td class="border-secondary small text-secondary">${h.detalle || '—'}</td>
        <td class="border-secondary small text-secondary">${h.incidencia_titulo || '—'}</td>
        <td class="border-secondary small text-secondary">${h.ip_origen || '—'}</td>
      </tr>`).join('');

    document.getElementById('tbodyHistorial').innerHTML =
      html || '<tr><td colspan="6" class="text-center text-secondary py-4">No hay registros con esos filtros</td></tr>';

    renderPaginacionHist(total, pagina, por_pagina);
  } catch(e) {
    document.getElementById('tbodyHistorial').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar</td></tr>';
  }
}

function renderPaginacionHist(total, pagina, por_pagina) {
  const tp = Math.ceil(total/por_pagina);
  if (tp<=1) { document.getElementById('paginacionHist').innerHTML=''; return; }
  let html = `<li class="page-item ${pagina===1?'disabled':''}"><a class="page-link bg-dark border-secondary text-light" href="#" onclick="cargarHistorial(${pagina-1})">«</a></li>`;
  for (let p=Math.max(1,pagina-2); p<=Math.min(tp,pagina+2); p++) {
    html += `<li class="page-item ${p===pagina?'active':''}"><a class="page-link ${p===pagina?'bg-danger border-danger':'bg-dark border-secondary text-light'}" href="#" onclick="cargarHistorial(${p})">${p}</a></li>`;
  }
  html += `<li class="page-item ${pagina===tp?'disabled':''}"><a class="page-link bg-dark border-secondary text-light" href="#" onclick="cargarHistorial(${pagina+1})">»</a></li>`;
  document.getElementById('paginacionHist').innerHTML = html;
}

function limpiarFiltrosHistorial() {
  ['filtroUsuario','filtroAccion','filtroDesde','filtroHasta'].forEach(id => document.getElementById(id).value = '');
  cargarHistorial();
}

// ── Init ──
inicializarBarraUsuario();
poblarFiltroUsuarios();
poblarFiltroAcciones();
cargarHistorial();
