// ════════════════════════════════════════════════════════
//  sidebar.js  —  Sidebar compartido para todas las páginas
//  Depende de: auth-guard.js (getUsuario, cerrarSesion, API)
//  Uso: <script src="./js/sidebar.js"></script>
//  Llamar initSidebar('nombre-pagina-activa') en DOMContentLoaded
// ════════════════════════════════════════════════════════

// CSS del sidebar inyectado una sola vez
(function inyectarEstilosSidebar() {
  if (document.getElementById('sidebar-styles')) return;
  const style = document.createElement('style');
  style.id = 'sidebar-styles';
  style.textContent = `
    :root {
      --bg-base:    #0d1117;
      --bg-surface: #161b22;
      --bg-hover:   #1f2937;
      --border:     rgba(139,148,158,.25);
      --text-muted: #8b949e;
      --accent:     #f85149;
      --sidebar-w:  220px;
    }

    * { box-sizing: border-box; }

    body {
      background: var(--bg-base);
      color: #e6edf3;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display: flex;
      min-height: 100vh;
      margin: 0;
    }

    /* ── Sidebar ── */
    #gi-sidebar {
      width: var(--sidebar-w);
      min-height: 100vh;
      background: var(--bg-surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0;
      z-index: 200;
      transition: transform .25s ease;
    }
    #gi-sidebar .sb-brand {
      padding: 18px 16px 14px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 10px;
      text-decoration: none;
    }
    #gi-sidebar .sb-brand-icon {
      background: var(--accent); border-radius: 8px;
      width: 32px; height: 32px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    #gi-sidebar .sb-brand-name { font-weight: 700; font-size: .95rem; color: #e6edf3; }
    #gi-sidebar .sb-brand-badge {
      font-size: .6rem; background: #1f3347; color: #58a6ff;
      padding: 2px 6px; border-radius: 4px; font-weight: 600;
    }

    .sb-section {
      padding: 8px 10px 2px;
      font-size: .65rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-muted);
      font-weight: 600;
    }
    .sb-link {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 14px;
      border-radius: 8px;
      margin: 1px 8px;
      color: #cdd9e5;
      text-decoration: none;
      font-size: .875rem;
      transition: background .15s;
    }
    .sb-link:hover  { background: var(--bg-hover); color: #fff; }
    .sb-link.active { background: var(--bg-hover); color: var(--accent); font-weight: 600; }
    .sb-link .bi    { font-size: 1rem; width: 18px; text-align: center; }
    .sb-link .sb-badge {
      margin-left: auto; font-size: .6rem; min-width: 18px;
      padding: 2px 5px; border-radius: 20px;
    }

    /* ── Bottom user card ── */
    #gi-sidebar-bottom {
      margin-top: auto;
      border-top: 1px solid var(--border);
      padding: 10px 8px;
    }
    .sb-user-card {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 10px; border-radius: 8px;
      cursor: pointer; transition: background .15s;
      position: relative;
    }
    .sb-user-card:hover { background: var(--bg-hover); }

    .sb-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: #1f3347;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: .85rem; color: #58a6ff; font-weight: 700;
      overflow: hidden;
    }
    .sb-avatar img {
      width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
    }
    .sb-user-info { overflow: hidden; flex: 1; min-width: 0; }
    .sb-user-info .sb-name {
      font-size: .8rem; font-weight: 600;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      color: #e6edf3;
    }
    .sb-user-info .sb-role { font-size: .68rem; color: var(--text-muted); }

    /* Dropdown del user card */
    .sb-user-dropdown {
      display: none;
      position: absolute;
      bottom: 100%; left: 0; right: 0;
      background: #1f2937;
      border: 1px solid var(--border);
      border-radius: 8px;
      margin-bottom: 4px;
      overflow: hidden;
      z-index: 300;
    }
    .sb-user-dropdown.open { display: block; }
    .sb-user-dropdown a,
    .sb-user-dropdown button {
      display: flex; align-items: center; gap: 8px;
      padding: 9px 14px;
      color: #cdd9e5; text-decoration: none;
      font-size: .83rem;
      background: none; border: none; width: 100%; cursor: pointer;
      transition: background .15s;
    }
    .sb-user-dropdown a:hover,
    .sb-user-dropdown button:hover { background: var(--bg-hover); }
    .sb-user-dropdown button { color: var(--accent); }
    .sb-divider { border-top: 1px solid var(--border); margin: 2px 0; }

    /* ── Main wrapper ── */
    #gi-main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    /* ── Topbar ── */
    #gi-topbar {
      height: 52px;
      background: var(--bg-surface);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px;
      position: sticky; top: 0; z-index: 100;
    }
    #gi-topbar .gi-page-title { font-size: 1rem; font-weight: 600; }
    .gi-topbar-actions { display: flex; align-items: center; gap: 14px; }

    .gi-notif-btn {
      position: relative; background: none; border: none;
      color: #cdd9e5; font-size: 1.2rem; cursor: pointer; padding: 4px;
    }
    .gi-notif-btn .notif-dot {
      position: absolute; top: 2px; right: 2px;
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--accent); display: none;
      border: 2px solid var(--bg-surface);
    }

    /* ── Sidebar toggle mobile ── */
    #gi-sidebar-toggle {
      display: none; background: none; border: none;
      color: #cdd9e5; font-size: 1.3rem; cursor: pointer;
    }

    @media (max-width: 768px) {
      #gi-sidebar { transform: translateX(-100%); }
      #gi-sidebar.open { transform: none; }
      #gi-main { margin-left: 0; }
      #gi-sidebar-toggle { display: block; }
    }
  `;
  document.head.appendChild(style);
})();

// ════════════════════════════════════════════════════════
//  HTML del sidebar
// ════════════════════════════════════════════════════════
function _buildSidebarHTML(paginaActiva, esAdmin, esSuperAdmin) {
  const links = [
    { id: 'index',       href: 'index.html',        icon: 'bi-speedometer2',      label: 'Dashboard'    },
    { id: 'incidencias', href: 'incidencias.html',   icon: 'bi-list-ul',           label: 'Incidencias'  },
    { id: 'registrar',   href: 'registrar.html',     icon: 'bi-plus-circle',       label: 'Registrar'    },
    { id: 'mis-reportes',href: 'mis-reportes.html',  icon: 'bi-file-earmark-text', label: 'Mis Reportes' },
    { id: 'mis-apoyos',  href: 'mis-apoyos.html',    icon: 'bi-hand-thumbs-up',    label: 'Mis Apoyos'   },
    { id: 'reportes',    href: 'reportes.html',       icon: 'bi-bar-chart',         label: 'Reportes'     },
    { id: 'perfil',      href: 'perfil.html',         icon: 'bi-person',            label: 'Mi Perfil'    },
  ];

  const adminLinks = [
    { id: 'admin',    href: 'admin.html',    icon: 'bi-inbox',        label: 'Incidencias',  badge: '<span class="sb-badge bg-danger text-white" id="sideIncBadge" style="display:none">0</span>' },
    { id: 'apoyos',   href: 'admin.html',    icon: 'bi-cash-coin',    label: 'Incentivos',   badge: '<span class="sb-badge" style="background:#3d2e00;color:#e3b341;" id="sideApoBadge" style="display:none">0</span>', onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('apoyos');else location.href='admin.html';" },
    { id: 'usuarios', href: 'admin.html',    icon: 'bi-people',       label: 'Usuarios',     onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('usuarios');else location.href='admin.html';" },
    { id: 'permisos', href: 'admin.html',    icon: 'bi-key',          label: 'Solicitar Permisos', onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('permisos');else location.href='admin.html';" },
    { id: 'historial',href: 'historial.html',icon: 'bi-clock-history',label: 'Historial'     },
  ];

  const superAdminLinks = [
    { id: 'superadmin', href: 'superadmin.html', icon: 'bi-shield-lock', label: 'Solicitudes y Permisos' },
  ];

  const renderLink = (l) => {
    const active = l.id === paginaActiva ? 'active' : '';
    const onclick = l.onclick ? `onclick="${l.onclick}"` : '';
    return `<a class="sb-link ${active}" href="${l.href}" ${onclick}>
      <i class="bi ${l.icon}"></i>${l.label}${l.badge ?? ''}
    </a>`;
  };

  const adminSection = esAdmin ? `
    <div class="sb-section" style="margin-top:8px;">Administración</div>
    ${adminLinks.map(renderLink).join('')}
  ` : '';

  const superAdminSection = esSuperAdmin ? `
    <div class="sb-section" style="margin-top:8px;">SuperAdmin</div>
    ${superAdminLinks.map(renderLink).join('')}
  ` : '';

  return `
    <a class="sb-brand" href="index.html">
      <div class="sb-brand-icon"><i class="bi bi-geo-alt-fill text-white"></i></div>
      <div>
        <div class="sb-brand-name">GeoIncidencias</div>
        ${esSuperAdmin ? '<div class="sb-brand-badge" style="background:#3d1f3d;color:#d291ff;">SUPERADMIN</div>' : (esAdmin ? '<div class="sb-brand-badge">ADMIN</div>' : '')}
      </div>
    </a>

    <div style="overflow-y:auto; flex:1; padding-bottom:8px;">
      <div class="sb-section">General</div>
      ${links.map(renderLink).join('')}
      ${adminSection}
      ${superAdminSection}
    </div>

    <div id="gi-sidebar-bottom">
      <div class="sb-user-card" id="sbUserCard" onclick="toggleSbDropdown()">
        <div class="sb-avatar" id="sbAvatar"><span id="sbAvatarLetra">?</span></div>
        <div class="sb-user-info">
          <div class="sb-name" id="sbNombre">—</div>
          <div class="sb-role" id="sbRol">—</div>
        </div>
        <i class="bi bi-three-dots-vertical text-secondary ms-auto" style="font-size:.9rem;"></i>
        <div class="sb-user-dropdown" id="sbDropdown">
          <a href="perfil.html"><i class="bi bi-person"></i>Mi Perfil</a>
          <div class="sb-divider"></div>
          <button id="sbBtnLogout"><i class="bi bi-box-arrow-right"></i>Cerrar sesión</button>
        </div>
      </div>
    </div>
  `;
}

// ════════════════════════════════════════════════════════
//  Títulos por página
// ════════════════════════════════════════════════════════
const _TITULOS = {
  'index':       'Dashboard',
  'incidencias': 'Incidencias',
  'registrar':   'Registrar Incidencia',
  'mis-reportes':'Mis Reportes',
  'mis-apoyos':  'Mis Apoyos',
  'reportes':    'Reportes',
  'perfil':      'Mi Perfil',
  'admin':       'Panel de Administración',
  'historial':   'Historial de Actividad',
  'superadmin':  'Solicitudes y Permisos',
};

// ════════════════════════════════════════════════════════
//  INIT PRINCIPAL
// ════════════════════════════════════════════════════════
function initSidebar(paginaActiva) {
  const u = getUsuario();
  if (!u) return;

  const esAdmin = u.rol === 'admin' || u.rol === 'superadmin';
  const esSuperAdmin = u.rol === 'superadmin';

  // 1. Crear el sidebar
  const sidebar = document.createElement('nav');
  sidebar.id = 'gi-sidebar';
  sidebar.innerHTML = _buildSidebarHTML(paginaActiva, esAdmin, esSuperAdmin);
  document.body.prepend(sidebar);

  // 2. Envolver contenido existente en #gi-main + topbar
  const contenidoExistente = Array.from(document.body.children).filter(el => el.id !== 'gi-sidebar');
  const main = document.createElement('div');
  main.id = 'gi-main';

  // Topbar
  const topbar = document.createElement('div');
  topbar.id = 'gi-topbar';
  topbar.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;">
      <button id="gi-sidebar-toggle" onclick="toggleGiSidebar()"><i class="bi bi-list"></i></button>
      <span class="gi-page-title">${_TITULOS[paginaActiva] ?? 'GeoIncidencias'}</span>
    </div>
    <div class="gi-topbar-actions">
      <button class="gi-notif-btn" id="btnNotificaciones" title="Notificaciones">
        <i class="bi bi-bell"></i>
        <span class="notif-dot" id="notifDot"></span>
        <span id="badgeNotificaciones" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;font-size:.6rem;top:0!important;right:0!important;left:auto!important;transform:none!important;position:absolute;">0</span>
      </button>
    </div>
  `;
  main.appendChild(topbar);

  contenidoExistente.forEach(el => main.appendChild(el));
  document.body.appendChild(main);

  // 3. Rellenar datos del usuario
  _cargarDatosUsuario(u);

  // 3b. Filtrar módulos del admin según sus permisos reales (superadmin ve todo)
  if (u.rol === 'admin') _filtrarModulosPorPermisos();

  // 4. Eventos
  document.getElementById('sbBtnLogout').addEventListener('click', cerrarSesion);
  document.addEventListener('click', (e) => {
    const card = document.getElementById('sbUserCard');
    const drop = document.getElementById('sbDropdown');
    if (card && drop && !card.contains(e.target)) drop.classList.remove('open');
  });

  // 5. Notificaciones (reusar las de auth-guard si existen)
  if (typeof crearPanelNotificaciones === 'function') crearPanelNotificaciones();
  if (typeof cargarContadorNotificaciones === 'function') cargarContadorNotificaciones();

  const btnNotif = document.getElementById('btnNotificaciones');
  if (btnNotif && typeof togglePanelNotificaciones === 'function') {
    btnNotif.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      togglePanelNotificaciones();
    });
  }
}

// ════════════════════════════════════════════════════════
//  Cargar datos de usuario en sidebar (nombre, rol, foto)
// ════════════════════════════════════════════════════════
function _cargarDatosUsuario(u) {
  const nombre = u.nombre ?? '—';
  const inicial = nombre.charAt(0).toUpperCase();

  const sbNombre = document.getElementById('sbNombre');
  const sbRol    = document.getElementById('sbRol');
  const sbLetra  = document.getElementById('sbAvatarLetra');
  const sbAvatar = document.getElementById('sbAvatar');

  if (sbNombre) sbNombre.textContent = nombre;
  if (sbRol)    sbRol.textContent    = u.rol === 'superadmin' ? 'Superadmin' : (u.rol === 'admin' ? 'Administrador' : 'Usuario');
  if (sbLetra)  sbLetra.textContent  = inicial;

  // Foto de perfil: si tiene foto_url la muestra, si no muestra la inicial
  const fotoUrl = u.foto_url;
  if (fotoUrl && sbAvatar) {
    const base = API.replace('/api', '');
    const src  = fotoUrl.startsWith('http') ? fotoUrl : `${base}/storage/${fotoUrl}`;
    sbAvatar.innerHTML = `<img src="${src}" alt="${nombre}" onerror="this.parentElement.innerHTML='<span>${inicial}</span>'" />`;
  }
}

// ════════════════════════════════════════════════════════
//  Filtrar links de administración según permisos reales
//  (solo aplica a rol='admin'; superadmin ve todo siempre)
// ════════════════════════════════════════════════════════
const _MODULO_POR_LINK_ID = {
  admin:     'incidencias',
  apoyos:    'incentivos',
  usuarios:  'usuarios',
  historial: 'historial',
};

async function _filtrarModulosPorPermisos() {
  try {
    const r = await fetch(`${API}/mis-permisos`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    const data = await r.json();
    const permisos = data.permisos ?? {};

    document.querySelectorAll('#gi-sidebar .sb-link').forEach(a => {
      const onclick = a.getAttribute('onclick') || '';
      const match = onclick.match(/cambiarTab\('(\w+)'\)/);
      if (!match) return;
      const tabId = match[1];
      if (tabId === 'permisos') return; // esa siempre visible para admin
      const modulo = _MODULO_POR_LINK_ID[tabId];
      if (!modulo) return;
      const tienePermiso = permisos[modulo]?.puede_ver;
      if (!tienePermiso) a.style.display = 'none';
    });
  } catch (e) {
    // Si falla, no se oculta nada (fail-safe: mejor mostrar de más que bloquear el acceso)
  }
}

// Función pública para actualizar la foto sin recargar (usada en perfil.js)
function actualizarFotoSidebar(fotoUrl) {
  const u = getUsuario();
  if (!u) return;
  const sbAvatar = document.getElementById('sbAvatar');
  if (!sbAvatar) return;
  if (fotoUrl) {
    const base = API.replace('/api', '');
    const src  = fotoUrl.startsWith('http') ? fotoUrl : `${base}/storage/${fotoUrl}`;
    const inicial = (u.nombre ?? '?').charAt(0).toUpperCase();
    sbAvatar.innerHTML = `<img src="${src}" alt="${u.nombre}" onerror="this.parentElement.innerHTML='<span>${inicial}</span>'" />`;
  } else {
    const inicial = (u.nombre ?? '?').charAt(0).toUpperCase();
    sbAvatar.innerHTML = `<span>${inicial}</span>`;
  }
}

// ════════════════════════════════════════════════════════
//  Helpers UI
// ════════════════════════════════════════════════════════
function toggleGiSidebar() {
  document.getElementById('gi-sidebar')?.classList.toggle('open');
}

function toggleSbDropdown() {
  document.getElementById('sbDropdown')?.classList.toggle('open');
}
