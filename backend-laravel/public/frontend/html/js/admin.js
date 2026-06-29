/* ═══════════════════════════════════════════════════════════
   admin.js — Panel de Administración · GeoIncidencias
   Depende de: Bootstrap 5, auth-guard.js
═══════════════════════════════════════════════════════════ */

// API definida en auth-guard.js
const token   = () => localStorage.getItem('token') ?? '';
const headers = () => ({ 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` });

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
};

function cambiarTab(tab) {
  ['incidencias', 'apoyos', 'usuarios'].forEach(t => {
    const panel = document.getElementById(`panel${capitalize(t)}`);
    const btn   = document.getElementById(`tab${capitalize(t)}Btn`);
    if (panel) panel.style.display = t === tab ? 'block' : 'none';
    if (btn)   btn.classList.toggle('active', t === tab);
  });

  const titulo = document.getElementById('topbarTitle');
  if (titulo) titulo.textContent = TAB_TITLES[tab] ?? 'Administración';

  if (tab === 'usuarios') cargarUsuarios();
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
  const u = JSON.parse(localStorage.getItem('usuario') ?? '{}');
  if (u.nombre) {
    document.getElementById('sideNombre').textContent = u.nombre;
    document.getElementById('sideRol').textContent    = u.rol === 'admin' ? 'Administrador' : 'Usuario';
    document.getElementById('sideAvatar').textContent = u.nombre.charAt(0).toUpperCase();
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

    // Actualizar contadores
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
  tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-muted)"><i class="bi bi-arrow-repeat me-2"></i>Cargando…</td></tr>';

  try {
    const r     = await fetch(`${API}/admin/usuarios?${params}`, { headers: headers() });
    const d     = await r.json();
    const items = d.data?.data ?? [];
    const meta  = d.data ?? {};

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-muted)">No se encontraron usuarios</td></tr>';
      document.getElementById('paginacionUsuarios').innerHTML = '';
      return;
    }

    tbody.innerHTML = items.map(u => `
      <tr>
        <td style="color:var(--text-muted);font-size:.78rem;">#${u.id_usuario}</td>
        <td><strong>${esc(u.nombre)} ${esc(u.apellido ?? '')}</strong></td>
        <td style="color:var(--text-muted);font-size:.82rem;">${esc(u.correo)}</td>
        <td>${rolBadge(u.rol)}</td>
        <td>${verifBadge(u.correo_verificado)}</td>
        <td>${activoBadge(u.activo)}</td>
        <td style="color:var(--text-muted);font-size:.75rem;">${fmtDate(u.created_at)}</td>
        <td>
          <button class="btn-icon me-1" title="${u.activo ? 'Desactivar' : 'Activar'}"
            onclick="toggleActivo(${u.id_usuario}, ${u.activo})">
            <i class="bi bi-${u.activo ? 'person-dash' : 'person-check'}"></i>
          </button>
          <button class="btn-icon" title="Cambiar a ${u.rol === 'admin' ? 'usuario' : 'admin'}"
            onclick="cambiarRol(${u.id_usuario}, '${u.rol}')">
            <i class="bi bi-shield-${u.rol === 'admin' ? 'minus' : 'plus'}"></i>
          </button>
        </td>
      </tr>
    `).join('');

    renderPaginacion(meta.current_page, meta.last_page, meta.total);
    cargarEstadisticasUsuarios();

  } catch {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-5">Error al cargar usuarios</td></tr>';
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
  initUsuarioActual();
  cargarIncidenciasPendientes();
  cargarApoyosPendientes();
  cargarNotificaciones();

  // Tabs
  document.getElementById('tabIncidenciasBtn').addEventListener('click', () => cambiarTab('incidencias'));
  document.getElementById('tabApoyosBtn').addEventListener('click',      () => cambiarTab('apoyos'));
  document.getElementById('tabUsuariosBtn').addEventListener('click',    () => cambiarTab('usuarios'));

  // Sidebar links (admin section)
  document.getElementById('linkAdmin').addEventListener('click',    (e) => { e.preventDefault(); cambiarTab('incidencias'); });
  document.getElementById('linkApoyos').addEventListener('click',   (e) => { e.preventDefault(); cambiarTab('apoyos'); });
  document.getElementById('linkUsuarios').addEventListener('click', (e) => { e.preventDefault(); cambiarTab('usuarios'); });

  // Sidebar mobile
  document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);

  // Filtros usuarios
  document.getElementById('filtBuscar').addEventListener('input',    aplicarFiltros);
  document.getElementById('filtActivo').addEventListener('change',   aplicarFiltros);
  document.getElementById('filtRol').addEventListener('change',      aplicarFiltros);
  document.getElementById('filtVerif').addEventListener('change',    aplicarFiltros);
  document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);

  // Modales — botones confirmar
  document.getElementById('btnConfirmarRechazoInc').addEventListener('click',   confirmarRechazoIncidencia);
  document.getElementById('btnConfirmarRechazoApoyo').addEventListener('click', confirmarRechazoApoyo);
});