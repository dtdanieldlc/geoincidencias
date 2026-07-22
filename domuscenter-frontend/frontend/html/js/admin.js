/* ═══════════════════════════════════════════════════════════
   admin.js — Panel de Administración · DomusCenter
   Depende de: Bootstrap 5, auth-guard.js
═══════════════════════════════════════════════════════════ */

// API definida en auth-guard.js
const token   = () => localStorage.getItem('gi_token') ?? '';
const headers = () => ({ 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` });

/* ══════════════════════════════════════════════════════════
   PERMISOS REALES DEL ADMIN — se cargan una vez al iniciar
   y controlan qué pestañas/botones aparecen de verdad
══════════════════════════════════════════════════════════ */
let misPermisosAdmin = {};

async function cargarMisPermisosAdmin() {
  try {
    const r = await fetch(`${API}/mis-permisos`, { headers: headers() });
    const data = await r.json();
    misPermisosAdmin = data.permisos ?? {};
  } catch (e) {
    misPermisosAdmin = {};
  }
}

function tienePermiso(modulo, accion = 'ver') {
  if (esSuperAdminActual()) return true;
  return !!misPermisosAdmin?.[modulo]?.[`puede_${accion}`];
}

function aplicarVisibilidadPorPermisos() {
  if (esSuperAdminActual()) return; // el superadmin ve y puede todo, sin restricciones

  const mapaTabs = [
    { tabId: 'tabIncidenciasBtn', linkId: 'linkAdmin',    modulo: 'incidencias', tab: 'incidencias' },
    { tabId: 'tabApoyosBtn',      linkId: 'linkApoyos',   modulo: 'incentivos',  tab: 'apoyos'       },
    { tabId: 'tabUsuariosBtn',    linkId: 'linkUsuarios', modulo: 'usuarios',    tab: 'usuarios'     },
  ];

  let primerTabVisible = null;
  mapaTabs.forEach(({ tabId, linkId, modulo, tab }) => {
    const puedeVer = tienePermiso(modulo, 'ver');
    const tabBtn = document.getElementById(tabId);
    const link = document.getElementById(linkId);
    if (tabBtn) tabBtn.style.display = puedeVer ? '' : 'none';
    if (link) link.style.display = puedeVer ? '' : 'none';
    if (puedeVer && !primerTabVisible) primerTabVisible = tab;
  });

  // Historial no es un tab dentro de admin.html, es un link a otra página
  const linkHistorial = document.getElementById('linkHistorial');
  if (linkHistorial) linkHistorial.style.display = tienePermiso('historial', 'ver') ? '' : 'none';

  // Si la pestaña activa por defecto (incidencias) no tiene permiso de ver, cambia a la primera disponible
  if (!tienePermiso('incidencias', 'ver')) {
    cambiarTab(primerTabVisible || 'incidencias');
  }
}

/* ══════════════════════════════════════════════════════════
   HEARTBEAT — presencia en tiempo real
══════════════════════════════════════════════════════════ */
function startHeartbeat() {
  const u = JSON.parse(localStorage.getItem('gi_usuario') ?? '{}');
  if (!u.id_usuario) return;
  const pagina = location.pathname.split('/').pop() || 'admin.html';

  function ping() {
    fetch(`${API}/admin/usuarios/${u.id_usuario}/presencia`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({ pagina }),
    }).catch(() => {});
  }
  ping();
  setInterval(ping, 30000); // cada 30 segundos
}

/* ══════════════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════════════ */
let _toastTimer;

function showToast(msg, type = 'success') {
  const colores = {
    success: { bg: '#e6f8ee', color: '#16a34a', border: '#2ea04326' },
    error:   { bg: '#fbe9e9', color: '#dc2626', border: '#dc262626' },
    warning: { bg: '#fef3e0', color: '#d97706', border: '#d9770626' },
  };
  const c = colores[type] ?? colores.success;
  const t = document.getElementById('toast');
  t.textContent = msg;
  Object.assign(t.style, {
    display: 'block',
    background: c.bg,
    color: c.color,
    border: `1px solid ${c.border}`,
    top: '16px', right: '16px', position: 'fixed',
    minWidth: '280px', padding: '12px 16px',
    borderRadius: '8px', fontSize: '.85rem', zIndex: 9999,
  });
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
}

/* ══════════════════════════════════════════════════════════
   TABS
══════════════════════════════════════════════════════════ */
const TAB_TITLES = {
  incidencias: 'Incidencias por revisar',
  apoyos:      'Incentivos por aprobar',
  usuarios:    'Gestión de Usuarios',
  permisos:    'Solicitar Permisos',
};

function cambiarTab(tab) {
  ['incidencias', 'apoyos', 'usuarios', 'permisos'].forEach(t => {
    const panel = document.getElementById(`panel${capitalize(t)}`);
    const btn   = document.getElementById(`tab${capitalize(t)}Btn`);
    if (panel) panel.style.display = t === tab ? 'block' : 'none';
    if (btn)   btn.classList.toggle('active', t === tab);
  });

  const titulo = document.querySelector('.gi-page-title');
  if (titulo) titulo.textContent = TAB_TITLES[tab] ?? 'Administración';

  _resaltarSidebarSegunTab(tab);

  if (tab === 'usuarios') cargarUsuarios();
  if (tab === 'permisos' && typeof initPanelPermisos === 'function') initPanelPermisos();
}

// El sidebar es compartido (sidebar.js) y solo sabe resaltar según la
// PÁGINA en la que estás, pero admin.html tiene 4 "páginas" en una sola
// (pestañas). Acá se corrige a mano cuál link debe verse activo según
// la pestaña realmente abierta.
const TAB_A_LINK_ID = { incidencias: 'linkAdmin', apoyos: 'linkApoyos', usuarios: 'linkUsuarios', permisos: 'linkPermisos' };
function _resaltarSidebarSegunTab(tab) {
  document.querySelectorAll('#gi-sidebar .sb-link').forEach(a => a.classList.remove('active'));
  const el = document.getElementById(TAB_A_LINK_ID[tab]);
  if (el) el.classList.add('active');
}

function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/* ══════════════════════════════════════════════════════════
   PESTAÑA "Solicitar Permisos" — solo visible para admin
   (el sidebar compartido, la navegación y el usuario logueado
   ya los maneja sidebar.js/auth-guard.js en todas las páginas)
══════════════════════════════════════════════════════════ */
function initUsuarioActual() {
  const u = JSON.parse(localStorage.getItem('gi_usuario') ?? '{}');
  const tabPermisosBtn = document.getElementById('tabPermisosBtn');
  if (tabPermisosBtn) tabPermisosBtn.style.display = u.rol === 'admin' ? 'inline-flex' : 'none';
}

/* ══════════════════════════════════════════════════════════
   INCIDENCIAS PENDIENTES
══════════════════════════════════════════════════════════ */
async function cargarIncidenciasPendientes() {
  const tbody = document.getElementById('tbodyPendIncidencias');
  try {
    const r     = await fetch(`${API}/incidencias/pendientes-aprobacion`, { headers: headers() });
    const d     = await r.json();
    const items = d.data ?? d ?? [];

    document.getElementById('cntPendIncidencias').textContent = items.length;
    const badge = document.getElementById('sideIncBadge');
    if (items.length > 0) { badge.textContent = items.length; badge.style.display = 'inline'; }
    else                  { badge.style.display = 'none'; }

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-muted)">No hay incidencias pendientes ✓</td></tr>';
      return;
    }

    const prioColor = { alta: '#dc2626', media: '#d97706', baja: '#16a34a' };

    tbody.innerHTML = items.map(i => `
      <tr>
        <td><input type="checkbox" class="chk-pend-inc" value="${i.id_incidencia}" onchange="actualizarBarraLoteInc()"></td>
        <td><strong>${esc(i.titulo)}</strong></td>
        <td style="color:var(--text-muted)">${esc(i.tipo_nombre ?? i.tipo ?? '—')}</td>
        <td style="color:var(--text-muted)">${esc(i.zona_nombre ?? i.zona ?? '—')}</td>
        <td>
          <span style="color:${prioColor[i.prioridad] ?? '#64748b'};font-weight:600;font-size:.8rem;">
            ${esc((i.prioridad ?? '—').toUpperCase())}
          </span>
        </td>
        <td style="color:var(--text-muted)">${esc(i.creador_nombre ?? i.usuario ?? '—')}</td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(i.created_at)}</td>
        <td>
          ${tienePermiso('incidencias', 'editar') ? `
          <button class="btn-icon success me-1" onclick="aprobarIncidencia(${i.id_incidencia})">
            <i class="bi bi-check2"></i> Aprobar
          </button>
          <button class="btn-icon danger" onclick="abrirModalRechazarInc(${i.id_incidencia})">
            <i class="bi bi-x"></i> Rechazar
          </button>` : `<span class="small" style="color:var(--text-muted)">Sin permiso para editar</span>`}
        </td>
      </tr>
    `).join('');
    actualizarBarraLoteInc();

  } catch {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-5">Error al cargar incidencias</td></tr>';
  }
}

// ── Selección en lote (Incidencias pendientes) ──
function toggleTodasPendInc(origen) {
  document.querySelectorAll('.chk-pend-inc').forEach(chk => chk.checked = origen.checked);
  actualizarBarraLoteInc();
}

function actualizarBarraLoteInc() {
  const seleccionadas = document.querySelectorAll('.chk-pend-inc:checked');
  const barra = document.getElementById('barraLoteIncidencias');
  if (!barra) return;
  barra.classList.toggle('d-none', seleccionadas.length === 0);
  document.getElementById('cntSeleccionadasInc').textContent = seleccionadas.length;
}

async function aprobarLoteIncidencias() {
  const ids = Array.from(document.querySelectorAll('.chk-pend-inc:checked')).map(c => Number(c.value));
  if (!ids.length) return;
  if (!(await confirmarAccion(`¿Aprobar ${ids.length} incidencia(s) seleccionada(s)?`, { titulo: 'Aprobar incidencias', textoBoton: 'Sí, aprobar', tipo: 'info' }))) return;
  try {
    await fetch(`${API}/incidencias/aprobar-lote`, {
      method: 'PUT', headers: { ...headers(), 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids }),
    });
    mostrarAlerta(`${ids.length} incidencia(s) aprobada(s).`, 'success');
    cargarIncidenciasPendientes();
  } catch { mostrarAlerta('Error al aprobar en lote.', 'danger'); }
}

async function rechazarLoteIncidencias() {
  const ids = Array.from(document.querySelectorAll('.chk-pend-inc:checked')).map(c => Number(c.value));
  if (!ids.length) return;
  const motivo = prompt(`Vas a rechazar ${ids.length} incidencia(s). Escribe el motivo (opcional):`, '');
  if (motivo === null) return; // canceló
  try {
    await fetch(`${API}/incidencias/rechazar-lote`, {
      method: 'PUT', headers: { ...headers(), 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids, motivo }),
    });
    mostrarAlerta(`${ids.length} incidencia(s) rechazada(s).`, 'success');
    cargarIncidenciasPendientes();
  } catch { mostrarAlerta('Error al rechazar en lote.', 'danger'); }
}

/* ══════════════════════════════════════════════════════════
   TODAS LAS INCIDENCIAS — cambiar estado (solo admin)
══════════════════════════════════════════════════════════ */
const ESTADOS_INCIDENCIA = [
  { id: 1, nombre: 'Pendiente'  },
  { id: 2, nombre: 'En proceso' },
  { id: 3, nombre: 'Resuelto'   },
  { id: 4, nombre: 'Cerrado'    },
];
let paginaTodasInc = 1;

async function cargarTodasIncidencias(pag = 1) {
  paginaTodasInc = pag;
  const tbody = document.getElementById('tbodyTodasIncidencias');
  try {
    const r = await fetch(`${API}/incidencias?todas=1&pagina=${pag}&por_pagina=10`, { headers: headers() });
    const d = await r.json();
    const items = d.datos ?? [];

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-muted)">No hay incidencias registradas</td></tr>';
      document.getElementById('paginacionTodasIncidencias').innerHTML = '';
      return;
    }

    const prioColor = { Alta: '#dc2626', Media: '#d97706', Baja: '#16a34a' };

    tbody.innerHTML = items.map(i => `
      <tr>
        <td><strong>${esc(i.titulo)}</strong></td>
        <td style="color:var(--text-muted)">${esc(i.tipo ?? '—')}</td>
        <td style="color:var(--text-muted)">${esc(i.zona ?? '—')}</td>
        <td>
          <span style="color:${prioColor[i.prioridad] ?? '#64748b'};font-weight:600;font-size:.8rem;">
            ${esc((i.prioridad ?? '—').toUpperCase())}
          </span>
        </td>
        <td>${badgeEstadoAdmin(i.estado)}</td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(i.fecha_ocurrencia)}</td>
        <td>
          ${tienePermiso('incidencias', 'editar')
            ? (i.estado === 'Cerrado' && !esSuperAdminActual()
                ? `<span class="small" style="color:var(--text-muted)" title="Solo el superadmin puede reabrir una incidencia cerrada">
                     <i class="bi bi-lock-fill me-1"></i>Cerrada
                   </span>`
                : `<select class="form-select form-select-sm border-secondary" style="background:#f4f7fb;width:auto;display:inline-block;"
                          onchange="cambiarEstadoIncidencia(${i.id_incidencia}, this.value)">
                     ${ESTADOS_INCIDENCIA.map(e => `<option value="${e.id}" ${e.nombre === i.estado ? 'selected' : ''}>${e.nombre}</option>`).join('')}
                   </select>`)
            : badgeEstadoAdmin(i.estado)}
        </td>
      </tr>
    `).join('');

    renderPaginacionTodasIncidencias(d.total ?? items.length, pag, 10);

  } catch {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">Error al cargar incidencias</td></tr>';
  }
}

function badgeEstadoAdmin(estado) {
  const colores = {
    'Pendiente':  { bg: 'rgba(248,81,73,.15)',  color: '#dc2626' },
    'En proceso': { bg: 'rgba(227,179,65,.15)', color: '#d97706' },
    'Resuelto':   { bg: 'rgba(63,185,80,.15)',  color: '#16a34a' },
    'Cerrado':    { bg: 'rgba(139,148,158,.15)',color: '#64748b' },
  };
  const c = colores[estado] ?? { bg: 'rgba(139,148,158,.15)', color: '#64748b' };
  return `<span class="badge rounded-pill" style="background:${c.bg};color:${c.color};padding:5px 10px;font-size:.72rem;">${esc(estado ?? '—')}</span>`;
}

function renderPaginacionTodasIncidencias(total, pagina, porPagina) {
  const cont = document.getElementById('paginacionTodasIncidencias');
  const tp = Math.ceil(total / porPagina);
  if (tp <= 1) { cont.innerHTML = ''; return; }
  let html = `<button class="page-btn" ${pagina===1?'disabled':''} onclick="cargarTodasIncidencias(${pagina-1})">«</button>`;
  for (let p = Math.max(1, pagina-2); p <= Math.min(tp, pagina+2); p++) {
    html += `<button class="page-btn ${p===pagina?'active':''}" onclick="cargarTodasIncidencias(${p})">${p}</button>`;
  }
  html += `<button class="page-btn" ${pagina===tp?'disabled':''} onclick="cargarTodasIncidencias(${pagina+1})">»</button>`;
  cont.innerHTML = html;
}

async function cambiarEstadoIncidencia(id, idEstado) {
  try {
    const r = await fetch(`${API}/incidencias/${id}`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({ id_estado_actual: parseInt(idEstado) }),
    });
    const d = await r.json();
    if (r.ok && d.ok) {
      showToast('Estado actualizado correctamente.', 'success');
      cargarTodasIncidencias(paginaTodasInc);
    } else {
      showToast(d.mensaje || 'No se pudo actualizar el estado.', 'error');
    }
  } catch {
    showToast('Error de conexión al actualizar estado.', 'error');
  }
}

async function aprobarIncidencia(id) {
  const r = await fetch(`${API}/incidencias/${id}/aprobar`, { method: 'PUT', headers: headers() });
  const d = await r.json();
  showToast(d.mensaje ?? 'Incidencia aprobada', d.ok ? 'success' : 'error');
  if (d.ok) cargarIncidenciasPendientes();
}

function abrirModalRechazarInc(id) {
  document.getElementById('rechazarIncId').value    = id;
  document.getElementById('rechazarIncMotivo').value = '';
  new bootstrap.Modal(document.getElementById('modalRechazarInc')).show();
}

async function confirmarRechazoIncidencia() {
  const id     = document.getElementById('rechazarIncId').value;
  const motivo = document.getElementById('rechazarIncMotivo').value.trim();
  if (!motivo) { showToast('Escribe el motivo del rechazo', 'warning'); return; }

  const r = await fetch(`${API}/incidencias/${id}/rechazar`, {
    method: 'PUT', headers: headers(), body: JSON.stringify({ motivo }),
  });
  const d = await r.json();
  bootstrap.Modal.getInstance(document.getElementById('modalRechazarInc')).hide();
  showToast(d.mensaje ?? 'Incidencia rechazada', d.ok ? 'success' : 'error');
  if (d.ok) cargarIncidenciasPendientes();
}

/* ══════════════════════════════════════════════════════════
   APOYOS / INCENTIVOS PENDIENTES
══════════════════════════════════════════════════════════ */
async function cargarApoyosPendientes() {
  const tbody = document.getElementById('tbodyPendApoyos');
  try {
    const r     = await fetch(`${API}/apoyos/pendientes`, { headers: headers() });
    const d     = await r.json();
    const items = d.data ?? d ?? [];

    document.getElementById('cntPendApoyos').textContent = items.length;
    const badge = document.getElementById('sideApoBadge');
    if (items.length > 0) { badge.textContent = items.length; badge.style.display = 'inline'; }
    else                  { badge.style.display = 'none'; }

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5" style="color:var(--text-muted)">No hay incentivos pendientes ✓</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(a => `
      <tr>
        <td><strong>${esc(a.incidencia_titulo ?? a.incidencia ?? '—')}</strong></td>
        <td style="color:var(--text-muted)">${esc(a.usuario_nombre ?? a.usuario ?? '—')}</td>
        <td style="color:var(--text-muted);font-size:.8rem;">${esc(a.comentario ?? '—')}</td>
        <td><strong style="color:#16a34a">$${parseFloat(a.monto_solicitado ?? a.monto ?? 0).toFixed(2)}</strong></td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(a.created_at)}</td>
        <td>
          ${tienePermiso('incentivos', 'editar') ? `
          <button class="btn-icon success me-1" onclick="aprobarApoyo(${a.id_apoyo})">
            <i class="bi bi-check2"></i> Aprobar
          </button>
          <button class="btn-icon danger" onclick="abrirModalRechazarApoyo(${a.id_apoyo})">
            <i class="bi bi-x"></i> Rechazar
          </button>` : `<span class="small" style="color:var(--text-muted)">Sin permiso para editar</span>`}
        </td>
      </tr>
    `).join('');

  } catch {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Error al cargar incentivos</td></tr>';
  }
}

async function aprobarApoyo(id) {
  const r = await fetch(`${API}/apoyos/${id}/aprobar`, { method: 'PUT', headers: headers() });
  const d = await r.json();
  showToast(d.mensaje ?? 'Incentivo aprobado', d.ok ? 'success' : 'error');
  if (d.ok) cargarApoyosPendientes();
}

function abrirModalRechazarApoyo(id) {
  document.getElementById('rechazarApoyoId').value          = id;
  document.getElementById('rechazarApoyoComentario').value  = '';
  new bootstrap.Modal(document.getElementById('modalRechazarApoyo')).show();
}

async function confirmarRechazoApoyo() {
  const id         = document.getElementById('rechazarApoyoId').value;
  const comentario = document.getElementById('rechazarApoyoComentario').value.trim();
  if (!comentario) { showToast('Escribe el motivo del rechazo', 'warning'); return; }

  const r = await fetch(`${API}/apoyos/${id}/rechazar`, {
    method: 'PUT', headers: headers(), body: JSON.stringify({ comentario }),
  });
  const d = await r.json();
  bootstrap.Modal.getInstance(document.getElementById('modalRechazarApoyo')).hide();
  showToast(d.mensaje ?? 'Incentivo rechazado', d.ok ? 'success' : 'error');
  if (d.ok) cargarApoyosPendientes();
}

/* ══════════════════════════════════════════════════════════
   USUARIOS — estadísticas
══════════════════════════════════════════════════════════ */
async function cargarEstadisticasUsuarios() {
  try {
    const r = await fetch(`${API}/admin/usuarios/estadisticas`, { headers: headers() });
    const d = await r.json();
    if (!d.ok) return;
    document.getElementById('uStatTotal').textContent   = d.data.total;
    document.getElementById('uStatActivos').textContent = d.data.activos;
    document.getElementById('uStatVerif').textContent   = d.data.verificados;
    document.getElementById('uStatMes').textContent     = d.data.nuevos_este_mes;
  } catch { /* silencioso */ }
}

/* ══════════════════════════════════════════════════════════
   USUARIOS — tabla con filtros y paginación
══════════════════════════════════════════════════════════ */
let uPagActual = 1;
let uFiltros   = {};
let _uDebounce;

async function cargarUsuarios(pagina = 1) {
  uPagActual = pagina;
  const params = new URLSearchParams({ page: pagina, por_pagina: 15, ...uFiltros });
  const tbody  = document.getElementById('tbodyUsuarios');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5" style="color:var(--text-muted)"><i class="bi bi-arrow-repeat me-2"></i>Cargando…</td></tr>';

  try {
    const r     = await fetch(`${API}/admin/usuarios?${params}`, { headers: headers() });
    const d     = await r.json();
    const items = d.data?.data ?? [];
    const meta  = d.data ?? {};

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5" style="color:var(--text-muted)">No se encontraron usuarios</td></tr>';
      document.getElementById('paginacionUsuarios').innerHTML = '';
      return;
    }

    const ahora = Date.now();

    tbody.innerHTML = items.map((u, idx) => {
      const numFila = (uPagActual - 1) * 15 + idx + 1;
      // ── Presencia online ──
      const ultimaPresencia = u.ultima_presencia_at
        ? new Date(u.ultima_presencia_at).getTime()
        : 0;
      const estaOnline  = ultimaPresencia > 0 && (ahora - ultimaPresencia) < 60000;
      const paginaLabel = (u.ultima_pagina ?? '').replace('.html', '') || '—';
      const badgeOnline = estaOnline
        ? `<span style="color:#16a34a;font-size:.78rem;font-weight:600;" title="En línea · ${esc(u.ultima_pagina ?? '')}">🟢 ${esc(paginaLabel)}</span>`
        : `<span style="color:#64748b;font-size:.78rem;">⚫ Desconectado</span>`;

      return `
        <tr>
          <td style="color:var(--text-muted);font-size:.78rem;" title="ID interno: ${u.id_usuario}">#${numFila}</td>
          <td><strong>${esc(u.nombre)} ${esc(u.apellido ?? '')}</strong></td>
          <td style="color:var(--text-muted);font-size:.82rem;">${esc(u.correo)}</td>
          <td>${rolBadge(u.rol)}</td>
          <td>${activoBadge(u.activo)}</td>
          <td>${badgeOnline}</td>
          <td style="color:var(--text-muted);font-size:.75rem;">${fmtDate(u.created_at)}</td>
          <td style="color:var(--text-muted);font-size:.75rem;">${fmtDatetime(u.ultima_presencia_at)}</td>
          <td>
            ${u.rol === 'superadmin' ? `
            <span class="small" style="color:var(--text-muted);"><i class="bi bi-shield-lock me-1"></i>No editable</span>
            ` : `
            ${tienePermiso('usuarios', 'editar') ? `
            <button class="btn-icon me-1" title="${u.activo ? 'Desactivar' : 'Activar'}"
              onclick="toggleActivo(${u.id_usuario}, ${u.activo})">
              <i class="bi bi-${u.activo ? 'person-dash' : 'person-check'}"></i>
            </button>` : ''}
            ${esSuperAdminActual() ? `
            <button class="btn-icon me-1" title="Cambiar a ${u.rol === 'admin' ? 'usuario' : 'admin'}"
              onclick="cambiarRol(${u.id_usuario}, '${u.rol}', '${esc(u.nombre)} ${esc(u.apellido ?? '')}')">
              <i class="bi bi-shield-${u.rol === 'admin' ? 'minus' : 'plus'}"></i>
            </button>` : ''}
            ${(esSuperAdminActual() && u.rol === 'admin') ? `
            <button class="btn-icon" title="Editar permisos"
              onclick="abrirModalPermisosUsuario(${u.id_usuario}, '${esc(u.nombre)} ${esc(u.apellido ?? '')}')">
              <i class="bi bi-key"></i>
            </button>` : ''}
            `}
          </td>
        </tr>
      `;
    }).join('');

    renderPaginacion(meta.current_page, meta.last_page, meta.total);
    cargarEstadisticasUsuarios();

  } catch {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-5">Error al cargar usuarios</td></tr>';
  }
}

function renderPaginacion(actual, total, totalItems) {
  const bar = document.getElementById('paginacionUsuarios');
  if (total <= 1) {
    bar.innerHTML = `<span class="page-info">${totalItems} resultado${totalItems !== 1 ? 's' : ''}</span>`;
    return;
  }
  let html = `<button class="page-btn" onclick="cargarUsuarios(${actual - 1})" ${actual === 1 ? 'disabled' : ''}>‹</button>`;
  for (let p = Math.max(1, actual - 2); p <= Math.min(total, actual + 2); p++) {
    html += `<button class="page-btn ${p === actual ? 'active' : ''}" onclick="cargarUsuarios(${p})">${p}</button>`;
  }
  html += `<button class="page-btn" onclick="cargarUsuarios(${actual + 1})" ${actual === total ? 'disabled' : ''}>›</button>`;
  html += `<span class="page-info">${totalItems} usuarios</span>`;
  bar.innerHTML = html;
}

function aplicarFiltros() {
  clearTimeout(_uDebounce);
  _uDebounce = setTimeout(() => {
    uFiltros = {};
    const b = document.getElementById('filtBuscar').value.trim();
    const a = document.getElementById('filtActivo').value;
    const r = document.getElementById('filtRol').value;
    const v = document.getElementById('filtVerif').value;
    if (b) uFiltros.buscar     = b;
    if (a) uFiltros.activo     = a;
    if (r) uFiltros.rol        = r;
    if (v) uFiltros.verificado = v;
    cargarUsuarios(1);
  }, 350);
}

function limpiarFiltros() {
  document.getElementById('filtBuscar').value = '';
  document.getElementById('filtActivo').value = '';
  document.getElementById('filtRol').value    = '';
  document.getElementById('filtVerif').value  = '';
  uFiltros = {};
  cargarUsuarios(1);
}

async function toggleActivo(id, activo) {
  const accion = activo ? 'desactivar' : 'activar';
  if (!(await confirmarAccion(`¿${capitalize(accion)} este usuario?`, {
    titulo: activo ? 'Desactivar usuario' : 'Activar usuario',
    textoBoton: `Sí, ${accion}`,
    tipo: activo ? 'warning' : 'info',
  }))) return;

  const r = await fetch(`${API}/admin/usuarios/${id}/activo`, { method: 'PUT', headers: headers() });
  const d = await r.json();
  showToast(d.mensaje ?? 'Actualizado', d.ok ? 'success' : 'error');
  if (d.ok) cargarUsuarios(uPagActual);
}

function cambiarRol(id, rolActual, nombre) {
  if (rolActual === 'admin') {
    _abrirModalDegradar(id, nombre);
  } else {
    _abrirModalPromover(id, nombre);
  }
}

/* ── Degradar admin → usuario (confirmación simple, sin permisos) ── */
function _initModalDegradar() {
  if (document.getElementById('modalDegradarRol')) return;
  const div = document.createElement('div');
  div.innerHTML = `
    <div class="modal fade" id="modalDegradarRol" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content border-0" style="background:#ffffff;">
          <div class="modal-header border-secondary">
            <h6 class="modal-title"><i class="bi bi-shield-minus me-2"></i>Quitar rol de administrador</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small mb-0">¿Quitar el rol de administrador a <strong id="degradarNombre"></strong>? Pasará a rol <strong>Usuario</strong> y perderá acceso al panel de administración (sus permisos guardados quedan sin efecto, no se borran).</p>
          </div>
          <div class="modal-footer border-secondary">
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="btnConfirmarDegradar">Quitar rol de admin</button>
          </div>
        </div>
      </div>
    </div>`;
  document.body.appendChild(div.firstElementChild);
}

let _modalDegradar = null;
let _idDegradarActual = null;

function _abrirModalDegradar(id, nombre) {
  _initModalDegradar();
  _idDegradarActual = id;
  document.getElementById('degradarNombre').textContent = nombre || `#${id}`;
  document.getElementById('btnConfirmarDegradar').onclick = async () => {
    const r = await fetch(`${API}/admin/usuarios/${id}/rol`, {
      method: 'PUT', headers: headers(), body: JSON.stringify({ rol: 'usuario' }),
    });
    const d = await r.json();
    showToast(d.mensaje ?? 'Rol actualizado', d.ok ? 'success' : 'error');
    if (d.ok) { cargarUsuarios(uPagActual); _modalDegradar.hide(); }
  };
  if (!_modalDegradar) _modalDegradar = new bootstrap.Modal(document.getElementById('modalDegradarRol'));
  _modalDegradar.show();
}

/* ── Promover usuario → admin CON selección de permisos iniciales ── */
function _initModalPromover() {
  if (document.getElementById('modalPromoverAdmin')) return;
  const div = document.createElement('div');
  div.innerHTML = `
    <div class="modal fade" id="modalPromoverAdmin" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content border-0" style="background:#ffffff;">
          <div class="modal-header border-secondary">
            <h6 class="modal-title"><i class="bi bi-shield-plus me-2"></i>Convertir en administrador a <span id="promoverNombre"></span></h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-secondary mb-3">Elige qué puede ver, editar o eliminar este nuevo admin. Puedes marcar "Todo" para darle acceso completo, o dejar módulos sin marcar para restringirlos.</p>
            <div class="mb-2">
              <button type="button" class="btn btn-outline-light btn-sm" id="btnMarcarTodoPromover"><i class="bi bi-check2-all me-1"></i>Marcar todo (ver+editar+eliminar)</button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarPromover">Limpiar todo</button>
            </div>
            <table style="width:100%; margin-bottom:8px;">
              <thead>
                <tr>
                  <th style="text-align:left;" class="small text-secondary">Módulo</th>
                  <th class="text-center small text-secondary">Ver</th>
                  <th class="text-center small text-secondary">Editar</th>
                  <th class="text-center small text-secondary">Eliminar</th>
                </tr>
              </thead>
              <tbody id="tbodyModulosPromover"></tbody>
            </table>
            <div id="msgPromoverAdmin" class="alert py-2 small mt-2" style="display:none;"></div>
          </div>
          <div class="modal-footer border-secondary">
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="btnConfirmarPromover"><i class="bi bi-shield-check me-1"></i>Convertir en admin</button>
          </div>
        </div>
      </div>
    </div>`;
  document.body.appendChild(div.firstElementChild);

  document.getElementById('btnMarcarTodoPromover').addEventListener('click', () => {
    document.querySelectorAll('#tbodyModulosPromover input[type="checkbox"]').forEach(c => c.checked = true);
  });
  document.getElementById('btnLimpiarPromover').addEventListener('click', () => {
    document.querySelectorAll('#tbodyModulosPromover input[type="checkbox"]').forEach(c => c.checked = false);
  });
}

let _modalPromover = null;
let _idPromoverActual = null;

function _abrirModalPromover(id, nombre) {
  _initModalPromover();
  _idPromoverActual = id;
  document.getElementById('promoverNombre').textContent = nombre || `#${id}`;

  const tbody = document.getElementById('tbodyModulosPromover');
  tbody.innerHTML = MODULOS_DISPONIBLES.map(m => `
    <tr>
      <td class="small">${m.label}</td>
      <td class="text-center">${_celdaPermiso(m, 'ver', 'ppv')}</td>
      <td class="text-center">${_celdaPermiso(m, 'editar', 'ppe')}</td>
      <td class="text-center">${_celdaPermiso(m, 'eliminar', 'ppd')}</td>
    </tr>
  `).join('');

  document.getElementById('btnConfirmarPromover').onclick = _confirmarPromover;

  if (!_modalPromover) _modalPromover = new bootstrap.Modal(document.getElementById('modalPromoverAdmin'));
  _modalPromover.show();
}

async function _confirmarPromover() {
  const id = _idPromoverActual;
  const msgEl = document.getElementById('msgPromoverAdmin');
  const btn = document.getElementById('btnConfirmarPromover');
  btn.disabled = true;

  const permisos = MODULOS_DISPONIBLES.map(m => ({
    modulo: m.id,
    puede_ver:      document.querySelector(`.ppv[data-modulo="${m.id}"]`)?.checked || false,
    puede_editar:   document.querySelector(`.ppe[data-modulo="${m.id}"]`)?.checked || false,
    puede_eliminar: document.querySelector(`.ppd[data-modulo="${m.id}"]`)?.checked || false,
  }));

  try {
    const rRol = await fetch(`${API}/admin/usuarios/${id}/rol`, {
      method: 'PUT', headers: headers(), body: JSON.stringify({ rol: 'admin' }),
    });
    const dRol = await rRol.json();
    if (!dRol.ok) {
      msgEl.className = 'alert alert-danger py-2 small mt-2';
      msgEl.textContent = dRol.mensaje || 'No se pudo cambiar el rol.';
      msgEl.style.display = 'block';
      return;
    }

    const rPerm = await fetch(`${API}/superadmin/permisos/${id}`, {
      method: 'PUT', headers: headers(), body: JSON.stringify({ permisos }),
    });
    const dPerm = await rPerm.json();

    showToast(`${dRol.mensaje} ${dPerm.ok ? '· Permisos asignados.' : '(revisa los permisos manualmente)'}`, 'success');
    _modalPromover.hide();
    cargarUsuarios(uPagActual);
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-2';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

/* ══════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════ */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return isNaN(dt) ? '—' : dt.toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtDatetime(d) {
  if (!d) return '—';
  const dt = new Date(d);
  if (isNaN(dt)) return '—';
  return dt.toLocaleString('es-EC', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function rolBadge(rol) {
  if (rol === 'superadmin') return '<span class="badge-admin" style="background:#f3e8fd;color:#9333ea;">Superadmin</span>';
  return rol === 'admin'
    ? '<span class="badge-admin">Admin</span>'
    : '<span class="badge-usuario">Usuario</span>';
}

function activoBadge(a) {
  return a
    ? '<span class="badge-activo">Activo</span>'
    : '<span class="badge-inactivo">Inactivo</span>';
}

function verifBadge(v) {
  return v
    ? '<span class="badge-verificado">Verificado</span>'
    : '<span class="badge-sin-verificar">Sin verificar</span>';
}

/* ══════════════════════════════════════════════════════════
   INIT — DOMContentLoaded
══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', async () => {
  exigirAdmin();
  initSidebar('admin');
  initUsuarioActual();
  startHeartbeat();
  await cargarMisPermisosAdmin();
  aplicarVisibilidadPorPermisos();
  cargarIncidenciasPendientes();
  cargarTodasIncidencias();
  cargarApoyosPendientes();

  document.getElementById('tabIncidenciasBtn').addEventListener('click', () => cambiarTab('incidencias'));
  document.getElementById('tabApoyosBtn').addEventListener('click',      () => cambiarTab('apoyos'));
  document.getElementById('tabUsuariosBtn').addEventListener('click',    () => cambiarTab('usuarios'));
  const tabPermisosBtnEl = document.getElementById('tabPermisosBtn');
  if (tabPermisosBtnEl) tabPermisosBtnEl.addEventListener('click', () => cambiarTab('permisos'));

  document.getElementById('filtBuscar').addEventListener('input',    aplicarFiltros);
  document.getElementById('filtActivo').addEventListener('change',   aplicarFiltros);
  document.getElementById('filtRol').addEventListener('change',      aplicarFiltros);
  document.getElementById('filtVerif').addEventListener('change',    aplicarFiltros);
  document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);

  document.getElementById('btnConfirmarRechazoInc').addEventListener('click',   confirmarRechazoIncidencia);
  document.getElementById('btnConfirmarRechazoApoyo').addEventListener('click', confirmarRechazoApoyo);

  // Si se llegó con ?tab=usuarios (o apoyos/permisos) desde otra página,
  // abrir directamente ese panel en lugar de quedarse en "Incidencias".
  const tabSolicitado = new URLSearchParams(location.search).get('tab');
  if (tabSolicitado && document.getElementById(`tab${capitalize(tabSolicitado)}Btn`)) {
    cambiarTab(tabSolicitado);
  }
});

/* ══════════════════════════════════════════════════════════
   PANEL: SOLICITAR PERMISOS (solo admin, no superadmin)
══════════════════════════════════════════════════════════ */
const MODULOS_DISPONIBLES = [
  { id: 'incidencias', label: 'Incidencias', acciones: ['ver', 'editar', 'eliminar'] },
  { id: 'usuarios',    label: 'Usuarios',    acciones: ['ver', 'editar'] },
  { id: 'incentivos',  label: 'Incentivos',  acciones: ['ver', 'editar'] },
  { id: 'historial',   label: 'Historial',   acciones: ['ver'] },
];

// Celda de una tabla de permisos: checkbox si el módulo realmente tiene esa
// acción implementada en el backend, o un guion si no aplica (ej. "Eliminar"
// para Usuarios/Incentivos, o "Editar"/"Eliminar" para Historial).
function _celdaPermiso(modulo, accion, claseCheckbox, checked = false) {
  if (!modulo.acciones.includes(accion)) {
    return `<span class="text-muted" style="opacity:.4;">—</span>`;
  }
  return `<input type="checkbox" class="form-check-input ${claseCheckbox}" data-modulo="${modulo.id}" style="width:1.15em;height:1.15em;cursor:pointer;" ${checked ? 'checked' : ''}>`;
}

let _permisosPanelInicializado = false;

async function initPanelPermisos() {
  cargarUsuariosObjetivoPermisos();
  cargarMisSolicitudesPermisos();

  if (_permisosPanelInicializado) return;
  _permisosPanelInicializado = true;

  const tbody = document.getElementById('tbodyModulosPermisos');
  if (tbody) {
    tbody.innerHTML = MODULOS_DISPONIBLES.map(m => `
      <tr>
        <td>${m.label}</td>
        <td class="text-center">${_celdaPermiso(m, 'ver', 'perm-ver')}</td>
        <td class="text-center">${_celdaPermiso(m, 'editar', 'perm-editar')}</td>
        <td class="text-center">${_celdaPermiso(m, 'eliminar', 'perm-eliminar')}</td>
      </tr>
    `).join('');
  }

  const btnEnviar = document.getElementById('btnEnviarSolicitudPermiso');
  if (btnEnviar) btnEnviar.addEventListener('click', enviarSolicitudPermiso);
}

async function cargarUsuariosObjetivoPermisos() {
  const select = document.getElementById('selectUsuarioObjetivoPermiso');
  if (!select) return;
  try {
    const r = await fetch(`${API}/catalogos/usuarios`, { headers: headers() });
    const usuarios = await r.json();
    select.innerHTML = '<option value="">Selecciona un usuario…</option>' +
      usuarios.map(u => `<option value="${u.id}">${u.nombre} — ${u.correo} (${u.rol === 'admin' ? 'Admin' : 'Usuario'})</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Error al cargar usuarios</option>';
  }
}

async function enviarSolicitudPermiso() {
  const idUsuarioObjetivo = document.getElementById('selectUsuarioObjetivoPermiso').value;
  const motivo = document.getElementById('motivoSolicitudPermiso').value.trim();
  const btn = document.getElementById('btnEnviarSolicitudPermiso');
  const msgEl = document.getElementById('msgSolicitudPermiso');

  if (!idUsuarioObjetivo) { mostrarMsgPermiso('Selecciona un usuario.', 'danger'); return; }
  if (motivo.length < 10)  { mostrarMsgPermiso('El motivo debe tener al menos 10 caracteres.', 'danger'); return; }

  const permisos_solicitados = MODULOS_DISPONIBLES.map(m => ({
    modulo: m.id,
    puede_ver:      document.querySelector(`.perm-ver[data-modulo="${m.id}"]`)?.checked || false,
    puede_editar:   document.querySelector(`.perm-editar[data-modulo="${m.id}"]`)?.checked || false,
    puede_eliminar: document.querySelector(`.perm-eliminar[data-modulo="${m.id}"]`)?.checked || false,
  })).filter(p => p.puede_ver || p.puede_editar || p.puede_eliminar);

  if (permisos_solicitados.length === 0) {
    mostrarMsgPermiso('Selecciona al menos un permiso.', 'danger');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Enviando…';

  try {
    const r = await fetch(`${API}/admin/solicitudes-permisos`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({
        id_usuario_objetivo: Number(idUsuarioObjetivo),
        motivo,
        permisos_solicitados,
      }),
    });
    const data = await r.json();
    if (data.ok) {
      mostrarMsgPermiso('Solicitud enviada al superadmin correctamente.', 'success');
      document.getElementById('motivoSolicitudPermiso').value = '';
      document.querySelectorAll('.perm-ver, .perm-editar, .perm-eliminar').forEach(c => c.checked = false);
      cargarMisSolicitudesPermisos();
    } else {
      mostrarMsgPermiso(data.mensaje || 'No se pudo enviar la solicitud.', 'danger');
    }
  } catch (e) {
    mostrarMsgPermiso('Error de conexión al enviar la solicitud.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar solicitud';
  }
}

function mostrarMsgPermiso(texto, tipo) {
  const msgEl = document.getElementById('msgSolicitudPermiso');
  if (!msgEl) return;
  msgEl.className = `alert alert-${tipo} py-2 small mt-2`;
  msgEl.textContent = texto;
  msgEl.style.display = 'block';
  setTimeout(() => { msgEl.style.display = 'none'; }, 5000);
}

const ESTADO_BADGE = {
  pendiente:  '<span class="badge" style="background:#fef3e0;color:#d97706;">Pendiente</span>',
  aprobado:   '<span class="badge" style="background:#e6f8ee;color:#16a34a;">Aprobado</span>',
  rechazado:  '<span class="badge" style="background:#fbe9e9;color:#dc2626;">Rechazado</span>',
  modificado: '<span class="badge" style="background:#e3f7f4;color:#0d9488;">Modificado</span>',
};

async function cargarMisSolicitudesPermisos() {
  const tbody = document.getElementById('tbodyMisSolicitudesPermisos');
  if (!tbody) return;
  try {
    const r = await fetch(`${API}/admin/solicitudes-permisos`, { headers: headers() });
    const data = await r.json();
    const lista = data.data?.data ?? [];
    if (lista.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">Aún no has enviado solicitudes.</td></tr>';
      return;
    }
    tbody.innerHTML = lista.map(s => `
      <tr>
        <td>${s.usuarioObjetivo ? `${s.usuarioObjetivo.nombre} ${s.usuarioObjetivo.apellido || ''}` : '—'}</td>
        <td>${(s.permisos_solicitados || []).map(p => p.modulo).join(', ')}</td>
        <td>${ESTADO_BADGE[s.estado] || s.estado}</td>
        <td>${s.respuesta_superadmin || '—'}</td>
        <td>${new Date(s.created_at).toLocaleDateString('es-EC')}</td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar solicitudes.</td></tr>';
  }
}

/* ══════════════════════════════════════════════════════════
   MODAL: Editar permisos de un admin directo desde la tabla
   de Usuarios (solo visible/usable para superadmin)
══════════════════════════════════════════════════════════ */
function esSuperAdminActual() {
  const u = JSON.parse(localStorage.getItem('gi_usuario') ?? '{}');
  return u.rol === 'superadmin';
}

let _modalPermisosUsuario = null;
let _idUsuarioPermisosModal = null;

function _initModalPermisosUsuario() {
  if (document.getElementById('modalPermisosUsuario')) return;

  const div = document.createElement('div');
  div.innerHTML = `
    <div class="modal fade" id="modalPermisosUsuario" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content border-0" style="background:#ffffff; border:1px solid rgba(139,148,158,.25) !important;">
          <div class="modal-header" style="border-bottom:1px solid rgba(139,148,158,.25);">
            <h6 class="modal-title mb-0"><i class="bi bi-key me-2" style="color:#9333ea;"></i>Permisos de <span id="modalPermisosNombre" class="fw-bold"></span></h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" style="padding:20px 24px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <p class="small text-secondary mb-0">Marca lo que este admin puede ver, editar o eliminar en cada módulo.</p>
              <div>
                <button type="button" class="btn btn-outline-light btn-sm" id="btnMarcarTodoModalPermisos"><i class="bi bi-check2-all me-1"></i>Todo</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarModalPermisos">Limpiar</button>
              </div>
            </div>
            <div id="alertaSinPermisosModal" class="alert py-2 small mb-3" style="display:none; background:#eef4f8; border:1px solid rgba(139,148,158,.25); color:#d97706;">
              <i class="bi bi-info-circle me-1"></i>Este usuario todavía no tiene ningún permiso asignado.
            </div>
            <div style="background:#f4f7fb; border:1px solid rgba(139,148,158,.25); border-radius:10px; overflow:hidden;">
              <table style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr style="background:#eef4f8;">
                    <th style="text-align:left; padding:10px 16px; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600;">Módulo</th>
                    <th style="text-align:center; padding:10px 16px; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600;">Ver</th>
                    <th style="text-align:center; padding:10px 16px; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600;">Editar</th>
                    <th style="text-align:center; padding:10px 16px; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600;">Eliminar</th>
                  </tr>
                </thead>
                <tbody id="tbodyModalPermisosUsuario"></tbody>
              </table>
            </div>
            <div id="msgModalPermisosUsuario" class="alert py-2 small mt-3" style="display:none;"></div>
          </div>
          <div class="modal-footer" style="border-top:1px solid rgba(139,148,158,.25);">
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="btnGuardarPermisosModal" style="background:#f3e8fd; border-color:#f3e8fd;">
              <i class="bi bi-check2 me-1"></i>Guardar permisos
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(div.firstElementChild);

  document.getElementById('btnGuardarPermisosModal').addEventListener('click', guardarPermisosModal);
  document.getElementById('btnMarcarTodoModalPermisos').addEventListener('click', () => {
    document.querySelectorAll('#tbodyModalPermisosUsuario input[type="checkbox"]').forEach(c => c.checked = true);
  });
  document.getElementById('btnLimpiarModalPermisos').addEventListener('click', () => {
    document.querySelectorAll('#tbodyModalPermisosUsuario input[type="checkbox"]').forEach(c => c.checked = false);
  });
  _modalPermisosUsuario = new bootstrap.Modal(document.getElementById('modalPermisosUsuario'));
}

async function abrirModalPermisosUsuario(idUsuario, nombre) {
  _initModalPermisosUsuario();
  _idUsuarioPermisosModal = idUsuario;
  document.getElementById('modalPermisosNombre').textContent = nombre;

  const tbody = document.getElementById('tbodyModalPermisosUsuario');
  tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3" style="color:var(--text-muted)">Cargando…</td></tr>`;
  _modalPermisosUsuario.show();

  try {
    const r = await fetch(`${API}/superadmin/permisos/${idUsuario}`, { headers: headers() });
    const data = await r.json();
    const permisos = data.permisos ?? [];
    const tieneAlgunPermiso = permisos.some(p => p.puede_ver || p.puede_editar || p.puede_eliminar);
    document.getElementById('alertaSinPermisosModal').style.display = tieneAlgunPermiso ? 'none' : 'block';

    tbody.innerHTML = MODULOS_DISPONIBLES.map((m, i) => {
      const p = permisos.find(x => x.modulo === m.id) || {};
      const bg = i % 2 === 0 ? 'transparent' : 'rgba(255,255,255,.02)';
      return `
        <tr style="background:${bg}; border-top:1px solid rgba(139,148,158,.12);">
          <td style="padding:10px 16px; color:#0b2340; font-size:.85rem;">${m.label}</td>
          <td style="text-align:center; padding:10px 16px;">${_celdaPermiso(m, 'ver', 'mpv', p.puede_ver)}</td>
          <td style="text-align:center; padding:10px 16px;">${_celdaPermiso(m, 'editar', 'mpe', p.puede_editar)}</td>
          <td style="text-align:center; padding:10px 16px;">${_celdaPermiso(m, 'eliminar', 'mpd', p.puede_eliminar)}</td>
        </tr>
      `;
    }).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">Error al cargar permisos.</td></tr>`;
  }
}

async function guardarPermisosModal() {
  if (!_idUsuarioPermisosModal) return;
  const msgEl = document.getElementById('msgModalPermisosUsuario');
  const btn = document.getElementById('btnGuardarPermisosModal');

  const permisos = MODULOS_DISPONIBLES.map(m => ({
    modulo: m.id,
    puede_ver:      document.querySelector(`.mpv[data-modulo="${m.id}"]`)?.checked || false,
    puede_editar:   document.querySelector(`.mpe[data-modulo="${m.id}"]`)?.checked || false,
    puede_eliminar: document.querySelector(`.mpd[data-modulo="${m.id}"]`)?.checked || false,
  }));

  btn.disabled = true;
  try {
    const r = await fetch(`${API}/superadmin/permisos/${_idUsuarioPermisosModal}`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({ permisos }),
    });
    const data = await r.json();
    if (data.ok) {
      msgEl.className = 'alert alert-success py-2 small mt-2';
      msgEl.textContent = data.mensaje;
      msgEl.style.display = 'block';
      setTimeout(() => { _modalPermisosUsuario.hide(); }, 900);
    } else {
      msgEl.className = 'alert alert-danger py-2 small mt-2';
      msgEl.textContent = data.mensaje || 'No se pudo guardar.';
      msgEl.style.display = 'block';
    }
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-2';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}
