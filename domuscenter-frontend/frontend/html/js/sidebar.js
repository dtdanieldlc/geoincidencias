// ════════════════════════════════════════════════════════
//  sidebar.js  —  Sidebar compartido para todas las páginas
//  Depende de: auth-guard.js (getUsuario, cerrarSesion, API)
//  Uso: <script src="./js/sidebar.js"></script>
//  Llamar initSidebar('nombre-pagina-activa') en DOMContentLoaded
// ════════════════════════════════════════════════════════

// Aplicar el tema guardado ANTES de pintar la página (evita parpadeo)
(function aplicarTemaGuardado() {
  if (localStorage.getItem('gi_theme') === 'dark') {
    document.documentElement.classList.add('dark-mode');
  }
})();

// CSS del sidebar inyectado una sola vez
(function inyectarEstilosSidebar() {
  if (document.getElementById('sidebar-styles')) return;
  const style = document.createElement('style');
  style.id = 'sidebar-styles';
  style.textContent = `
    :root {
      --navy:       #0b2340;
      --navy-2:     #123a63;
      --teal:       #14b8a6;
      --teal-dark:  #0d9488;
      --bg-page:    #f4f7fb;
      --bg-surface: #ffffff;
      --bg-hover:   #eef4f8;
      --border:     rgba(11,35,64,.08);
      --text:       #0b2340;
      --text-muted: #64748b;
      --accent:     #dc2626;
      --sidebar-w:  220px;
    }

    html.dark-mode #gi-main {
      filter: invert(93%) hue-rotate(180deg) contrast(115%);
      background: #f4f7fb;
    }
    html.dark-mode #gi-main img,
    html.dark-mode #gi-main .leaflet-container,
    html.dark-mode #gi-main video,
    html.dark-mode #gi-main iframe {
      filter: invert(93%) hue-rotate(180deg) contrast(115%);
    }

    * { box-sizing: border-box; }

    body {
      background: var(--bg-page);
      color: var(--text);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display: flex;
      min-height: 100vh;
      margin: 0;
      overflow-x: hidden;
      max-width: 100vw;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    body::-webkit-scrollbar { display: none; width: 0; height: 0; }
    html { scrollbar-width: none; -ms-overflow-style: none; overflow-x: hidden; }
    html::-webkit-scrollbar { display: none; width: 0; height: 0; }

    /* ── Sidebar ── */
    #gi-sidebar {
      width: var(--sidebar-w);
      height: 100vh;
      height: 100dvh;
      background: linear-gradient(170deg, var(--navy) 0%, var(--navy-2) 60%, #0c4f47 150%);
      border-right: 1px solid rgba(255,255,255,.06);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0;
      z-index: 1200;
      overflow: hidden;
    }
    /* Menú compacto + sin barra visible */
    #gi-sidebar, #gi-sidebar * {
      scrollbar-width: none !important;
      -ms-overflow-style: none !important;
    }
    #gi-sidebar::-webkit-scrollbar,
    #gi-sidebar *::-webkit-scrollbar {
      width: 0 !important;
      height: 0 !important;
      background: transparent !important;
      display: none !important;
    }

    .sb-brand {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 12px 10px;
      text-decoration: none !important;
      border-bottom: 1px solid rgba(255,255,255,.08);
      flex-shrink: 0;
    }
    .sb-brand-icon {
      width: 32px; height: 32px; border-radius: 8px;
      background: rgba(255,255,255,.08);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; flex-shrink: 0;
    }
    .sb-brand-icon img { width: 100%; height: 100%; object-fit: contain; }
    .sb-brand-name {
      font-weight: 700; font-size: .9rem; color: #fff; line-height: 1.15;
    }
    .sb-brand-badge {
      font-size: .55rem; font-weight: 700;
      letter-spacing: .05em; text-transform: uppercase;
      padding: 1px 6px; border-radius: 999px;
      background: rgba(20,184,166,.22); color: #5eead4;
      width: fit-content; margin-top: 1px;
    }

    .sb-section {
      padding: 8px 12px 3px;
      font-size: .6rem;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: rgba(255,255,255,.38);
      font-weight: 600;
    }

    .sb-link {
      display: flex; align-items: center; gap: 8px;
      padding: 6px 10px;
      margin: 0 6px;
      border-radius: 8px;
      color: rgba(255,255,255,.75);
      text-decoration: none !important;
      font-size: .8rem; font-weight: 500;
      transition: background .15s, color .15s;
    }
    .sb-link i { font-size: .95rem; width: 1.1rem; text-align: center; flex-shrink: 0; }
    .sb-link:hover { background: rgba(255,255,255,.08); color: #fff; }
    .sb-link.active {
      background: rgba(20,184,166,.22);
      color: #fff;
    }
    .sb-link.active i { color: #5eead4; }
    .sb-badge {
      margin-left: auto;
      font-size: .65rem; font-weight: 700;
      padding: 2px 7px; border-radius: 999px;
    }

    /* Footer usuario */
    #gi-sidebar-bottom {
      flex-shrink: 0;
      padding: 8px 10px 10px;
      border-top: 1px solid rgba(255,255,255,.08);
      position: relative;
    }
    .sb-user-card {
      display: flex; align-items: center; gap: 8px;
      padding: 6px 8px;
      border-radius: 10px;
      background: rgba(255,255,255,.06);
      cursor: pointer;
      position: relative;
      transition: background .15s;
    }
    .sb-user-card:hover { background: rgba(255,255,255,.1); }
    .sb-avatar {
      width: 30px; height: 30px; border-radius: 8px;
      background: linear-gradient(135deg, #14b8a6, #0d9488);
      color: #fff; font-weight: 700; font-size: .85rem;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; flex-shrink: 0;
    }
    .sb-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .sb-user-info { flex: 1; min-width: 0; }
    .sb-name {
      font-size: .75rem; font-weight: 600; color: #fff;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .sb-role {
      font-size: .68rem; color: rgba(255,255,255,.45);
      text-transform: capitalize;
    }
    .sb-user-dropdown {
      display: none;
      position: absolute;
      left: 0; right: 0; bottom: calc(100% + 6px);
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 28px rgba(11,35,64,.18);
      padding: 6px;
      z-index: 50;
    }
    .sb-user-dropdown.open { display: block; }
    .sb-user-dropdown a,
    .sb-user-dropdown button {
      display: flex; align-items: center; gap: 8px;
      width: 100%;
      padding: 8px 10px;
      border: none; background: none;
      border-radius: 8px;
      font-size: .85rem; color: var(--text);
      text-decoration: none; cursor: pointer;
      text-align: left;
    }
    .sb-user-dropdown a:hover,
    .sb-user-dropdown button:hover { background: var(--bg-hover); }
    .sb-user-dropdown button { color: #dc2626; }
    .sb-divider { height: 1px; background: var(--border); margin: 4px 0; }

    /* ── Main + topbar ── */
    #gi-main {
      margin-left: var(--sidebar-w);
      flex: 1;
      min-width: 0;
      min-height: 100vh;
      max-width: 100%;
      background: var(--bg-page);
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    #gi-main::-webkit-scrollbar { display: none; width: 0; height: 0; }

    #gi-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 24px 8px;
      gap: 12px;
      flex-shrink: 0;
    }
    .gi-page-title {
      font-weight: 700;
      font-size: 1.05rem;
      color: var(--text);
    }
    .gi-topbar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    #gi-sidebar-toggle {
      display: none;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 10px;
      width: 38px; height: 38px;
      align-items: center; justify-content: center;
      color: var(--navy);
      cursor: pointer;
      font-size: 1.2rem;
    }
    .gi-theme-btn, #btnNotificaciones {
      background: none; border: none;
      color: var(--text-muted);
      font-size: 1.15rem; cursor: pointer;
      padding: 8px; border-radius: 10px;
      display: inline-flex; align-items: center; justify-content: center;
      transition: background .15s, color .15s;
      position: relative;
    }
    .gi-theme-btn:hover, #btnNotificaciones:hover {
      background: var(--bg-hover); color: var(--navy);
    }
    #badgeNotif {
      position: absolute; top: 2px; right: 2px;
      font-size: .6rem; min-width: 16px; height: 16px;
      border-radius: 999px; background: #dc2626; color: #fff;
      display: none; align-items: center; justify-content: center;
      font-weight: 700; padding: 0 4px;
    }

    /* Backdrop móvil */
    #gi-sidebar-backdrop {
      display: none;
      position: fixed; inset: 0;
      background: rgba(11,35,64,.4);
      z-index: 1190;
    }
    #gi-sidebar-backdrop.open { display: block; }

    @media (max-width: 900px) {
      #gi-sidebar {
        transform: translateX(-105%);
        transition: transform .25s ease;
        width: min(var(--sidebar-w), 86vw);
      }
      #gi-sidebar.open { transform: translateX(0); }
      #gi-main { margin-left: 0; width: 100%; max-width: 100vw; }
      #gi-sidebar-toggle { display: inline-flex; }
      #gi-topbar { padding: 10px 12px 6px; }
      .gi-page-title { font-size: .95rem; }
    }
    @media (max-width: 576px) {
      #gi-main .container-fluid { padding-left: .75rem !important; padding-right: .75rem !important; }
    }
`;
  document.head.appendChild(style);
})();

// ════════════════════════════════════════════════════════
//  HTML del sidebar
// ════════════════════════════════════════════════════════
function _buildSidebarHTML(paginaActiva, esAdmin, esSuperAdmin, esEncargado) {
  const links = [
    { id: 'index',       href: 'index.html',        icon: 'bi-speedometer2',      label: 'Dashboard', soloAdmin: true },
    { id: 'mensajes',    href: 'mensajes.html',     icon: 'bi-chat-dots',         label: 'Mensajes', soloAdmin: true, badge: '<span class="sb-badge bg-danger text-white" id="sideMsgBadge" style="display:none">0</span>' },
    { id: 'incidencias', href: 'incidencias.html',   icon: 'bi-list-ul',           label: 'Incidencias'  },
    { id: 'registrar',   href: 'registrar.html',     icon: 'bi-plus-circle',       label: 'Registrar'    },
    { id: 'mis-reportes',href: 'mis-reportes.html',  icon: 'bi-file-earmark-text', label: 'Mis Reportes' },
    { id: 'reportes',    href: 'reportes.html',       icon: 'bi-bar-chart',         label: 'Reportes', soloAdmin: true },
    { id: 'perfil',      href: 'perfil.html',         icon: 'bi-person',            label: 'Mi Perfil'    },
  ];

  const adminLinks = [
    { id: 'admin',    href: 'admin.html',    icon: 'bi-inbox',        label: 'Incidencias',  linkId: 'linkAdmin',    badge: '<span class="sb-badge bg-danger text-white" id="sideIncBadge" style="display:none">0</span>' },
    { id: 'usuarios', href: 'admin.html?tab=usuarios',    icon: 'bi-people',       label: 'Usuarios',     linkId: 'linkUsuarios', onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('usuarios');else location.href='admin.html?tab=usuarios';" },
    { id: 'permisos', href: 'admin.html?tab=permisos',    icon: 'bi-key',          label: 'Solicitar Permisos', linkId: 'linkPermisos', onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('permisos');else location.href='admin.html?tab=permisos';" },
    { id: 'departamentos', href: 'admin.html?tab=departamentos', icon: 'bi-diagram-3', label: 'Departamentos', onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('departamentos');else location.href='admin.html?tab=departamentos';" },
    { id: 'sucursales', href: 'admin.html?tab=sucursales', icon: 'bi-building', label: 'Sucursales', soloSuperAdmin: true, onclick: "event.preventDefault();if(typeof cambiarTab==='function')cambiarTab('sucursales');else location.href='admin.html?tab=sucursales';" },
    { id: 'historial',href: 'historial.html',icon: 'bi-clock-history',label: 'Historial',    linkId: 'linkHistorial' },
  ];

  const superAdminLinks = [
    { id: 'superadmin', href: 'superadmin.html', icon: 'bi-shield-lock', label: 'Solicitudes y Permisos' },
  ];

  const renderLink = (l) => {
    const active = l.id === paginaActiva ? 'active' : '';
    const onclick = l.onclick ? `onclick="${l.onclick}"` : '';
    const idAttr = l.linkId ? `id="${l.linkId}"` : '';
    return `<a class="sb-link ${active}" ${idAttr} href="${l.href}" ${onclick}>
      <i class="bi ${l.icon}"></i>${l.label}${l.badge ?? ''}
    </a>`;
  };

  const adminSection = esAdmin ? `
    <div class="sb-section" style="margin-top:8px;">Administración</div>
    ${adminLinks.filter(l => !(esSuperAdmin && l.id === 'permisos') && !(l.soloSuperAdmin && !esSuperAdmin)).map(renderLink).join('')}
  ` : '';

  const encargadoSection = esEncargado ? `
    <div class="sb-section" style="margin-top:8px;">Mi departamento</div>
    <a class="sb-link ${paginaActiva==='incidencias'||paginaActiva==='admin'?'active':''}" href="incidencias.html">
      <i class="bi bi-inbox"></i><span>Cola de trabajo</span>
    </a>
    <a class="sb-link ${paginaActiva==='mensajes'?'active':''}" href="mensajes.html">
      <i class="bi bi-chat-dots"></i><span>Mensajes</span>
    </a>
  ` : '';

  const superAdminSection = esSuperAdmin ? `
    <div class="sb-section" style="margin-top:8px;">SuperAdmin</div>
    ${superAdminLinks.map(renderLink).join('')}
  ` : '';

  return `
    <a class="sb-brand" href="index.html">
      <div class="sb-brand-icon"><img src="../img/logo_domus_center.png" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:8px;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><i class="bi bi-geo-alt-fill text-white" style="display:none;"></i></div>
      <div>
        <div class="sb-brand-name">DomusCenter</div>
        ${esSuperAdmin ? '<div class="sb-brand-badge" style="background:rgba(168,85,247,.2);color:#d8b4fe;">SUPERADMIN</div>' : (esAdmin ? '<div class="sb-brand-badge">ADMIN</div>' : '')}
      </div>
    </a>

    <div style="overflow-y:auto; flex:1; padding-bottom:4px; scrollbar-width:none; -ms-overflow-style:none;">
      <div class="sb-section">General</div>
      ${links.filter(l => !l.soloAdmin || esAdmin).map(renderLink).join('')}
      ${adminSection}
      ${encargadoSection}
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
  'mensajes':    'Mensajes',
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
function _actualizarIconoTema() {
  const icono = document.getElementById('iconoTema');
  if (!icono) return;
  const esOscuro = document.documentElement.classList.contains('dark-mode');
  icono.className = esOscuro ? 'bi bi-sun' : 'bi bi-moon-stars';
}

function initSidebar(paginaActiva) {
  const u = getUsuario();
  if (!u) return;

  const esAdmin = u.rol === 'admin' || u.rol === 'superadmin';
  const esSuperAdmin = u.rol === 'superadmin';
  const esEncargado = u.rol === 'encargado';

  // 1. Crear el sidebar
  const sidebar = document.createElement('nav');
  sidebar.id = 'gi-sidebar';
  sidebar.innerHTML = _buildSidebarHTML(paginaActiva, esAdmin, esSuperAdmin, esEncargado);

  // Hard-hide Sucursales para quien no sea superadmin (por si quedó en DOM)
  if (!esSuperAdmin) {
    sidebar.querySelectorAll('a[href*="tab=sucursales"], a[onclick*="sucursales"]').forEach(a => {
      a.style.display = 'none';
      a.remove();
    });
  }
  document.body.prepend(sidebar);

  // 1b. Backdrop para cerrar el sidebar tocando fuera (solo móvil)
  const backdrop = document.createElement('div');
  backdrop.id = 'gi-sidebar-backdrop';
  backdrop.addEventListener('click', () => {
    sidebar.classList.remove('open');
    backdrop.classList.remove('open');
  });
  document.body.prepend(backdrop);

  // 2. Envolver contenido existente en #gi-main + topbar
  // Los modales de Bootstrap (position:fixed) NO se mueven a #gi-main:
  // si quedan dentro de un contenedor con filter (el modo oscuro usa
  // filter:invert), el navegador rompe su position:fixed y quedan mal
  // ubicados / con el backdrop bloqueando los clics.
  const contenidoExistente = Array.from(document.body.children).filter(el => el.id !== 'gi-sidebar' && !el.classList.contains('modal'));
  const main = document.createElement('div');
  main.id = 'gi-main';

  // Topbar
  const topbar = document.createElement('div');
  topbar.id = 'gi-topbar';
  topbar.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;">
      <button id="gi-sidebar-toggle" onclick="toggleGiSidebar()"><i class="bi bi-list"></i></button>
      <span class="gi-page-title">${_TITULOS[paginaActiva] ?? 'DomusCenter'}</span>
    </div>
    <div class="gi-topbar-actions">
      <button class="gi-theme-btn" id="btnTema" title="Cambiar tema">
        <i class="bi bi-moon-stars" id="iconoTema"></i>
      </button>
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

  // 4b. Modo oscuro
  _actualizarIconoTema();
  document.getElementById('btnTema').addEventListener('click', () => {
    document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('gi_theme', document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
    _actualizarIconoTema();
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

  // 6. Badge de mensajes no leídos
  _actualizarBadgeMensajes();
  setInterval(_actualizarBadgeMensajes, 30000);
}

async function _actualizarBadgeMensajes() {
  const badge = document.getElementById('sideMsgBadge');
  if (!badge) return;
  try {
    const r = await fetchAPI(`${API}/chat/conversaciones`);
    const conversaciones = await r.json();
    const total = Array.isArray(conversaciones) ? conversaciones.reduce((s, c) => s + (c.no_leidos || 0), 0) : 0;
    badge.textContent = total > 99 ? '99+' : total;
    badge.style.display = total > 0 ? 'inline-block' : 'none';
  } catch (e) { /* silencioso */ }
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
  if (sbRol)    sbRol.textContent    = u.rol === 'superadmin' ? 'Superadmin' : (u.rol === 'admin' ? 'Administrador' : (u.rol === 'encargado' ? 'Encargado' : 'Usuario'));
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
  document.getElementById('gi-sidebar-backdrop')?.classList.toggle('open');
}

function toggleSbDropdown() {
  document.getElementById('sbDropdown')?.classList.toggle('open');
}
