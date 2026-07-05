// frontend/js/auth-guard.js
const API = 'https://geoincidencias-production.up.railway.app/api';

function getToken()   { return localStorage.getItem('gi_token'); }
function getUsuario() { try { return JSON.parse(localStorage.getItem('gi_usuario')); } catch(e) { return null; } }

function cerrarSesion() {
  localStorage.removeItem('gi_token');
  localStorage.removeItem('gi_usuario');
  window.location.href = 'login.html';
}

function exigirSesion() {
  if (!getToken() || !getUsuario()) window.location.href = 'login.html';
}

function exigirAdmin() {
  exigirSesion();
  const u = getUsuario();
  if (!u || (u.rol !== 'admin' && u.rol !== 'superadmin')) window.location.href = 'index.html';
}

function exigirSuperAdmin() {
  exigirSesion();
  const u = getUsuario();
  if (!u || u.rol !== 'superadmin') window.location.href = 'index.html';
}

async function fetchAPI(url, opciones = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${getToken()}`,
    ...(opciones.headers || {}),
  };
  const res = await fetch(url, { ...opciones, headers });
  if (res.status === 401 || res.status === 403) {
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) { cerrarSesion(); throw new Error('Sesión expirada'); }
    throw new Error(data.mensaje || 'Acceso denegado');
  }
  return res;
}

// ── Panel de notificaciones ──
function crearPanelNotificaciones() {
  if (document.getElementById('panelNotificaciones')) return;

  const panel = document.createElement('div');
  panel.id = 'panelNotificaciones';
  panel.style.cssText = `
    display:none; position:fixed; top:60px; right:16px; width:340px; max-height:480px;
    background:#ffffff; border:1px solid rgba(11,35,64,.08); border-radius:10px;
    box-shadow:0 8px 32px rgba(0,0,0,.5); z-index:9999; overflow:hidden; flex-direction:column;
  `;
  panel.innerHTML = `
    <div style="padding:12px 16px; border-bottom:1px solid rgba(11,35,64,.08); display:flex; justify-content:space-between; align-items:center;">
      <span style="font-weight:600; color:#0b2340;">🔔 Notificaciones</span>
      <button onclick="marcarTodasLeidas()" style="background:none;border:none;color:#0d9488;font-size:.8rem;cursor:pointer;">Marcar todas leídas</button>
    </div>
    <div id="listaNotificaciones" style="overflow-y:auto; max-height:380px; padding:8px 0;">
      <div style="text-align:center;padding:24px;color:#64748b;">Cargando...</div>
    </div>
  `;
  document.body.appendChild(panel);

  // Cerrar al hacer clic fuera
  document.addEventListener('click', (e) => {
    const btn = document.getElementById('btnNotificaciones');
    if (!panel.contains(e.target) && btn && !btn.contains(e.target)) {
      panel.style.display = 'none';
    }
  });
}

function togglePanelNotificaciones() {
  const panel = document.getElementById('panelNotificaciones');
  if (!panel) return;
  const visible = panel.style.display === 'flex';
  panel.style.display = visible ? 'none' : 'flex';
  if (!visible) cargarNotificaciones();
}

async function cargarNotificaciones() {
  const lista = document.getElementById('listaNotificaciones');
  if (!lista) return;
  try {
    const r = await fetchAPI(`${API}/notificaciones`);
    const datos = await r.json();
    if (!datos.length) {
      lista.innerHTML = '<div style="text-align:center;padding:24px;color:#64748b;"><i class="bi bi-bell-slash" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Sin notificaciones</div>';
      return;
    }
    lista.innerHTML = datos.map(n => `
      <div onclick="marcarNotifLeida(${n.id_notificacion}, this)"
           style="padding:10px 16px; border-bottom:1px solid rgba(11,35,64,.08); cursor:pointer;
                  background:${n.leida ? 'transparent' : 'rgba(20,184,166,.08)'};
                  transition:background .2s;"
           onmouseover="this.style.background='rgba(11,35,64,.04)'"
           onmouseout="this.style.background='${n.leida ? 'transparent' : 'rgba(20,184,166,.08)'}'">
        <div style="display:flex;gap:10px;align-items:flex-start;">
          <span style="font-size:1.2rem;margin-top:2px;">${n.leida ? '🔘' : '🔵'}</span>
          <div style="flex:1;min-width:0;">
            <div style="color:#0b2340;font-size:.85rem;line-height:1.4;">${n.mensaje}</div>
            <div style="color:#64748b;font-size:.75rem;margin-top:4px;">${new Date(n.fecha).toLocaleString('es-EC')}</div>
          </div>
        </div>
      </div>
    `).join('');
  } catch(e) {
    lista.innerHTML = '<div style="text-align:center;padding:24px;color:#dc2626;">Error al cargar</div>';
  }
}

async function marcarNotifLeida(id, el) {
  try {
    await fetchAPI(`${API}/notificaciones/${id}/leida`, { method: 'PUT' });
    el.style.background = 'transparent';
    el.querySelector('span').textContent = '🔘';
    el.onmouseout = () => el.style.background = 'transparent';
    await cargarContadorNotificaciones();
  } catch(e) {}
}

async function marcarTodasLeidas() {
  try {
    await fetchAPI(`${API}/notificaciones/marcar-todas`, { method: 'PUT' });
    await cargarNotificaciones();
    await cargarContadorNotificaciones();
  } catch(e) {}
}

async function cargarContadorNotificaciones() {
  try {
    const r = await fetchAPI(`${API}/notificaciones/no-leidas`);
    const d = await r.json();
    const badge = document.getElementById('badgeNotificaciones');
    if (badge) {
      if (d.total > 0) { badge.textContent = d.total; badge.style.display = 'inline-block'; }
      else badge.style.display = 'none';
    }
  } catch(e) {}
}

// ── Barra de usuario ──
function inicializarBarraUsuario() {
  const u = getUsuario();
  if (!u) return;

  const nombreEl = document.getElementById('nombreUsuarioActual');
  if (nombreEl) nombreEl.textContent = u.nombre;
  const rolEl = document.getElementById('rolUsuarioActual');
  if (rolEl) rolEl.textContent = u.rol === 'superadmin' ? 'Superadmin' : (u.rol === 'admin' ? 'Administrador' : 'Usuario');

  if (u.rol !== 'admin' && u.rol !== 'superadmin') {
    document.querySelectorAll('.solo-admin').forEach(el => el.style.display = 'none');
  }

  const btnLogout = document.getElementById('btnCerrarSesion');
  if (btnLogout) btnLogout.addEventListener('click', cerrarSesion);

  // Botón notificaciones
  const btnNotif = document.getElementById('btnNotificaciones');
  if (btnNotif) {
    btnNotif.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      togglePanelNotificaciones();
    });
  }

  crearPanelNotificaciones();
  cargarContadorNotificaciones();

  // Actualizar foto en sidebar si existe
  if (typeof actualizarFotoSidebar === 'function') {
    actualizarFotoSidebar(u.foto_url ?? null);
  }

  // Actualizar contador cada 60 segundos
  setInterval(cargarContadorNotificaciones, 60000);
}
