/* ═══════════════════════════════════════════════════════════
   admin.js — Panel de Administración · GeoIncidencias
   Depende de: Bootstrap 5, auth-guard.js
═══════════════════════════════════════════════════════════ */

// API definida en auth-guard.js
const token   = () => localStorage.getItem('gi_token') ?? '';
const headers = () => ({ 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` });

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
    success: { bg: '#0d4429', color: '#3fb950', border: '#2ea04326' },
    error:   { bg: '#3d1212', color: '#f85149', border: '#f8514926' },
    warning: { bg: '#3d2e00', color: '#e3b341', border: '#e3b34126' },
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

  const titulo = document.getElementById('topbarTitle');
  if (titulo) titulo.textContent = TAB_TITLES[tab] ?? 'Administración';

  if (tab === 'usuarios') cargarUsuarios();
  if (tab === 'permisos' && typeof initPanelPermisos === 'function') initPanelPermisos();
}

function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/* ══════════════════════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════════════════════ */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ══════════════════════════════════════════════════════════
   USUARIO ACTUAL (sidebar inferior)
══════════════════════════════════════════════════════════ */
function initUsuarioActual() {
  const u = JSON.parse(localStorage.getItem('gi_usuario') ?? '{}');
  if (u.nombre) {
    document.getElementById('sideNombre').textContent = u.nombre;
    document.getElementById('sideRol').textContent    = u.rol === 'superadmin' ? 'Superadmin' : (u.rol === 'admin' ? 'Administrador' : 'Usuario');
    document.getElementById('sideAvatar').textContent = u.nombre.charAt(0).toUpperCase();
    const badgeEl = document.getElementById('brandBadge');
    if (badgeEl) badgeEl.textContent = u.rol === 'superadmin' ? 'SUPERADMIN' : 'ADMIN';
    const tabPermisosBtn = document.getElementById('tabPermisosBtn');
    if (tabPermisosBtn) tabPermisosBtn.style.display = u.rol === 'admin' ? 'inline-flex' : 'none';

    const linkPermisos = document.getElementById('linkPermisos');
    if (linkPermisos) linkPermisos.style.display = u.rol === 'admin' ? 'flex' : 'none';

    const esSuperAdmin = u.rol === 'superadmin';
    const linkSuperAdmin = document.getElementById('linkSuperAdmin');
    const navSectionSuperAdmin = document.getElementById('navSectionSuperAdmin');
    if (linkSuperAdmin) linkSuperAdmin.style.display = esSuperAdmin ? 'flex' : 'none';
    if (navSectionSuperAdmin) navSectionSuperAdmin.style.display = esSuperAdmin ? 'block' : 'none';
  }

  document.getElementById('btnCerrarSesion').addEventListener('click', async () => {
    await fetch(`${API}/auth/logout`, { method: 'POST', headers: headers() });
    localStorage.clear();
    location.href = 'login.html';
  });
}

/* ══════════════════════════════════════════════════════════
   NOTIFICACIONES (punto rojo)
══════════════════════════════════════════════════════════ */
async function cargarNotificaciones() {
  try {
    const r = await fetch(`${API}/notificaciones/no-leidas`, { headers: headers() });
    const d = await r.json();
    const cnt = Array.isArray(d) ? d.length : (d.data?.length ?? 0);
    if (cnt > 0) document.getElementById('notifDot').style.display = 'block';
  } catch { /* silencioso */ }
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
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-muted)">No hay incidencias pendientes ✓</td></tr>';
      return;
    }

    const prioColor = { alta: '#f85149', media: '#e3b341', baja: '#3fb950' };

    tbody.innerHTML = items.map(i => `
      <tr>
        <td><strong>${esc(i.titulo)}</strong></td>
        <td style="color:var(--text-muted)">${esc(i.tipo_nombre ?? i.tipo ?? '—')}</td>
        <td style="color:var(--text-muted)">${esc(i.zona_nombre ?? i.zona ?? '—')}</td>
        <td>
          <span style="color:${prioColor[i.prioridad] ?? '#8b949e'};font-weight:600;font-size:.8rem;">
            ${esc((i.prioridad ?? '—').toUpperCase())}
          </span>
        </td>
        <td style="color:var(--text-muted)">${esc(i.creador_nombre ?? i.usuario ?? '—')}</td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(i.created_at)}</td>
        <td>
          <button class="btn-icon success me-1" onclick="aprobarIncidencia(${i.id_incidencia})">
            <i class="bi bi-check2"></i> Aprobar
          </button>
          <button class="btn-icon danger" onclick="abrirModalRechazarInc(${i.id_incidencia})">
            <i class="bi bi-x"></i> Rechazar
          </button>
        </td>
      </tr>
    `).join('');

  } catch {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">Error al cargar incidencias</td></tr>';
  }
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

    const prioColor = { Alta: '#f85149', Media: '#e3b341', Baja: '#3fb950' };

    tbody.innerHTML = items.map(i => `
      <tr>
        <td><strong>${esc(i.titulo)}</strong></td>
        <td style="color:var(--text-muted)">${esc(i.tipo ?? '—')}</td>
        <td style="color:var(--text-muted)">${esc(i.zona ?? '—')}</td>
        <td>
          <span style="color:${prioColor[i.prioridad] ?? '#8b949e'};font-weight:600;font-size:.8rem;">
            ${esc((i.prioridad ?? '—').toUpperCase())}
          </span>
        </td>
        <td>${badgeEstadoAdmin(i.estado)}</td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(i.fecha_ocurrencia)}</td>
        <td>
          <select class="form-select form-select-sm border-secondary text-white" style="background:#0d1117;width:auto;display:inline-block;"
                  onchange="cambiarEstadoIncidencia(${i.id_incidencia}, this.value)">
            ${ESTADOS_INCIDENCIA.map(e => `<option value="${e.id}" ${e.nombre === i.estado ? 'selected' : ''}>${e.nombre}</option>`).join('')}
          </select>
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
    'Pendiente':  { bg: 'rgba(248,81,73,.15)',  color: '#f87171' },
    'En proceso': { bg: 'rgba(227,179,65,.15)', color: '#e3b341' },
    'Resuelto':   { bg: 'rgba(63,185,80,.15)',  color: '#3fb950' },
    'Cerrado':    { bg: 'rgba(139,148,158,.15)',color: '#8b949e' },
  };
  const c = colores[estado] ?? { bg: 'rgba(139,148,158,.15)', color: '#8b949e' };
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
        <td><strong style="color:#3fb950">$${parseFloat(a.monto_solicitado ?? a.monto ?? 0).toFixed(2)}</strong></td>
        <td style="color:var(--text-muted);font-size:.78rem;">${fmtDate(a.created_at)}</td>
        <td>
          <button class="btn-icon success me-1" onclick="aprobarApoyo(${a.id_apoyo})">
            <i class="bi bi-check2"></i> Aprobar
          </button>
          <button class="btn-icon danger" onclick="abrirModalRechazarApoyo(${a.id_apoyo})">
            <i class="bi bi-x"></i> Rechazar
          </button>
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

    tbody.innerHTML = items.map(u => {
      // ── Presencia online ──
      const ultimaPresencia = u.ultima_presencia_at
        ? new Date(u.ultima_presencia_at).getTime()
        : 0;
      const estaOnline  = ultimaPresencia > 0 && (ahora - ultimaPresencia) < 60000;
      const paginaLabel = (u.ultima_pagina ?? '').replace('.html', '') || '—';
      const badgeOnline = estaOnline
        ? `<span style="color:#3fb950;font-size:.78rem;font-weight:600;" title="En línea · ${esc(u.ultima_pagina ?? '')}">🟢 ${esc(paginaLabel)}</span>`
        : `<span style="color:#8b949e;font-size:.78rem;">⚫ Desconectado</span>`;

      return `
        <tr>
          <td style="color:var(--text-muted);font-size:.78rem;">#${u.id_usuario}</td>
          <td><strong>${esc(u.nombre)} ${esc(u.apellido ?? '')}</strong></td>
          <td style="color:var(--text-muted);font-size:.82rem;">${esc(u.correo)}</td>
          <td>${rolBadge(u.rol)}</td>
          <td>${activoBadge(u.activo)}</td>
          <td>${badgeOnline}</td>
          <td style="color:var(--text-muted);font-size:.75rem;">${fmtDate(u.created_at)}</td>
          <td style="color:var(--text-muted);font-size:.75rem;">${fmtDatetime(u.ultima_presencia_at)}</td>
          <td>
            <button class="btn-icon me-1" title="${u.activo ? 'Desactivar' : 'Activar'}"
              onclick="toggleActivo(${u.id_usuario}, ${u.activo})">
              <i class="bi bi-${u.activo ? 'person-dash' : 'person-check'}"></i>
            </button>
            <button class="btn-icon me-1" title="Cambiar a ${u.rol === 'admin' ? 'usuario' : 'admin'}"
              onclick="cambiarRol(${u.id_usuario}, '${u.rol}')">
              <i class="bi bi-shield-${u.rol === 'admin' ? 'minus' : 'plus'}"></i>
            </button>
            ${(esSuperAdminActual() && u.rol === 'admin') ? `
            <button class="btn-icon" title="Editar permisos"
              onclick="abrirModalPermisosUsuario(${u.id_usuario}, '${esc(u.nombre)} ${esc(u.apellido ?? '')}')">
              <i class="bi bi-key"></i>
            </button>` : ''}
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
  if (!confirm(`¿${capitalize(accion)} este usuario?`)) return;

  const r = await fetch(`${API}/admin/usuarios/${id}/activo`, { method: 'PUT', headers: headers() });
  const d = await r.json();
  showToast(d.mensaje ?? 'Actualizado', d.ok ? 'success' : 'error');
  if (d.ok) cargarUsuarios(uPagActual);
}

async function cambiarRol(id, rolActual) {
  const nuevoRol = rolActual === 'admin' ? 'usuario' : 'admin';
  if (!confirm(`¿Cambiar rol a "${nuevoRol}"?`)) return;

  const r = await fetch(`${API}/admin/usuarios/${id}/rol`, {
    method: 'PUT', headers: headers(), body: JSON.stringify({ rol: nuevoRol }),
  });
  const d = await r.json();
  showToast(d.mensaje ?? 'Rol actualizado', d.ok ? 'success' : 'error');
  if (d.ok) cargarUsuarios(uPagActual);
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
document.addEventListener('DOMContentLoaded', () => {
  exigirAdmin();
  initUsuarioActual();
  startHeartbeat();
  cargarIncidenciasPendientes();
  cargarTodasIncidencias();
  cargarApoyosPendientes();
  cargarNotificaciones();

  document.getElementById('tabIncidenciasBtn').addEventListener('click', () => cambiarTab('incidencias'));
  document.getElementById('tabApoyosBtn').addEventListener('click',      () => cambiarTab('apoyos'));
  document.getElementById('tabUsuariosBtn').addEventListener('click',    () => cambiarTab('usuarios'));
  const tabPermisosBtnEl = document.getElementById('tabPermisosBtn');
  if (tabPermisosBtnEl) tabPermisosBtnEl.addEventListener('click', () => cambiarTab('permisos'));

  document.getElementById('linkAdmin').addEventListener('click',    (e) => { e.preventDefault(); cambiarTab('incidencias'); });
  document.getElementById('linkApoyos').addEventListener('click',   (e) => { e.preventDefault(); cambiarTab('apoyos'); });
  document.getElementById('linkUsuarios').addEventListener('click', (e) => { e.preventDefault(); cambiarTab('usuarios'); });
  const linkPermisosEl = document.getElementById('linkPermisos');
  if (linkPermisosEl) linkPermisosEl.addEventListener('click', (e) => { e.preventDefault(); cambiarTab('permisos'); });

  document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);

  document.getElementById('filtBuscar').addEventListener('input',    aplicarFiltros);
  document.getElementById('filtActivo').addEventListener('change',   aplicarFiltros);
  document.getElementById('filtRol').addEventListener('change',      aplicarFiltros);
  document.getElementById('filtVerif').addEventListener('change',    aplicarFiltros);
  document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);

  document.getElementById('btnConfirmarRechazoInc').addEventListener('click',   confirmarRechazoIncidencia);
  document.getElementById('btnConfirmarRechazoApoyo').addEventListener('click', confirmarRechazoApoyo);
});

/* ══════════════════════════════════════════════════════════
   PANEL: SOLICITAR PERMISOS (solo admin, no superadmin)
══════════════════════════════════════════════════════════ */
const MODULOS_DISPONIBLES = [
  { id: 'dashboard',   label: 'Dashboard'   },
  { id: 'incidencias', label: 'Incidencias' },
  { id: 'usuarios',    label: 'Usuarios'    },
  { id: 'incentivos',  label: 'Incentivos'  },
  { id: 'apoyos',      label: 'Apoyos'      },
  { id: 'reportes',    label: 'Reportes'    },
  { id: 'historial',   label: 'Historial'   },
];

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
        <td class="text-center"><input type="checkbox" class="form-check-input perm-ver"      data-modulo="${m.id}"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input perm-editar"   data-modulo="${m.id}"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input perm-eliminar" data-modulo="${m.id}"></td>
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
    const r = await fetch(`${API}/admin/usuarios?rol=admin`, { headers: headers() });
    const data = await r.json();
    const usuarios = data.data?.data ?? data.data ?? [];
    select.innerHTML = '<option value="">Selecciona un usuario…</option>' +
      usuarios.map(u => `<option value="${u.id_usuario}">${u.nombre} ${u.apellido || ''} — ${u.correo}</option>`).join('');
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
  pendiente:  '<span class="badge" style="background:#3d2e00;color:#e3b341;">Pendiente</span>',
  aprobado:   '<span class="badge" style="background:#0d3321;color:#3fb950;">Aprobado</span>',
  rechazado:  '<span class="badge" style="background:#3d1f1f;color:#f85149;">Rechazado</span>',
  modificado: '<span class="badge" style="background:#1f3347;color:#58a6ff;">Modificado</span>',
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
        <div class="modal-content border-0" style="background:#161b22;">
          <div class="modal-header border-secondary">
            <h6 class="modal-title"><i class="bi bi-key me-2"></i>Permisos de <span id="modalPermisosNombre"></span></h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <table style="width:100%; margin-bottom:14px;">
              <thead>
                <tr>
                  <th style="text-align:left;" class="small text-secondary">Módulo</th>
                  <th class="text-center small text-secondary">Ver</th>
                  <th class="text-center small text-secondary">Editar</th>
                  <th class="text-center small text-secondary">Eliminar</th>
                </tr>
              </thead>
              <tbody id="tbodyModalPermisosUsuario"></tbody>
            </table>
            <div id="msgModalPermisosUsuario" class="alert py-2 small mt-2" style="display:none;"></div>
          </div>
          <div class="modal-footer border-secondary">
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="btnGuardarPermisosModal">
              <i class="bi bi-check2 me-1"></i>Guardar permisos
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(div.firstElementChild);

  document.getElementById('btnGuardarPermisosModal').addEventListener('click', guardarPermisosModal);
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

    tbody.innerHTML = MODULOS_DISPONIBLES.map(m => {
      const p = permisos.find(x => x.modulo === m.id) || {};
      return `
        <tr>
          <td class="small">${m.label}</td>
          <td class="text-center"><input type="checkbox" class="form-check-input mpv" data-modulo="${m.id}" ${p.puede_ver ? 'checked' : ''}></td>
          <td class="text-center"><input type="checkbox" class="form-check-input mpe" data-modulo="${m.id}" ${p.puede_editar ? 'checked' : ''}></td>
          <td class="text-center"><input type="checkbox" class="form-check-input mpd" data-modulo="${m.id}" ${p.puede_eliminar ? 'checked' : ''}></td>
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