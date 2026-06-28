// frontend/js/auth-guard.js
// Se incluye en TODAS las páginas protegidas (todas excepto login.html)
// Verifica sesión, expone el usuario actual y ofrece fetchAPI() con el token ya incluido.

// Backend Laravel corriendo en otro puerto (php artisan serve = 8000 por defecto).
// Si sirves el frontend desde el mismo dominio/puerto que Laravel, puedes usar '/api'.
const API = 'https://geoincidencias-production.up.railway.app/api';

function getToken()   { return localStorage.getItem('gi_token'); }
function getUsuario() { try { return JSON.parse(localStorage.getItem('gi_usuario')); } catch(e) { return null; } }

function cerrarSesion() {
  localStorage.removeItem('gi_token');
  localStorage.removeItem('gi_usuario');
  window.location.href = 'login.html';
}

// Redirige a login si no hay sesión válida. Llamar al inicio de cada página protegida.
function exigirSesion() {
  if (!getToken() || !getUsuario()) {
    window.location.href = 'login.html';
  }
}

// Si la página es exclusiva de admin, redirige si el usuario no lo es.
function exigirAdmin() {
  exigirSesion();
  const u = getUsuario();
  if (!u || u.rol !== 'admin') {
    window.location.href = 'index.html';
  }
}

// Wrapper de fetch que agrega el token automáticamente y maneja 401/403.
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

// Pinta el nombre del usuario y botón logout / oculta enlaces admin si no aplica.
function inicializarBarraUsuario() {
  const u = getUsuario();
  if (!u) return;
  const nombreEl = document.getElementById('nombreUsuarioActual');
  if (nombreEl) nombreEl.textContent = u.nombre;
  const rolEl = document.getElementById('rolUsuarioActual');
  if (rolEl) rolEl.textContent = u.rol === 'admin' ? 'Administrador' : 'Usuario';

  // Oculta enlaces marcados como solo-admin si el usuario no lo es
  if (u.rol !== 'admin') {
    document.querySelectorAll('.solo-admin').forEach(el => el.style.display = 'none');
  }

  const btnLogout = document.getElementById('btnCerrarSesion');
  if (btnLogout) btnLogout.addEventListener('click', cerrarSesion);

  cargarContadorNotificaciones();
}

async function cargarContadorNotificaciones() {
  try {
    const r = await fetchAPI(`${API}/notificaciones/no-leidas`);
    const d = await r.json();
    const badge = document.getElementById('badgeNotificaciones');
    if (badge) {
      if (d.total > 0) { badge.textContent = d.total; badge.style.display='inline-block'; }
      else badge.style.display = 'none';
    }
  } catch(e) {}
}
