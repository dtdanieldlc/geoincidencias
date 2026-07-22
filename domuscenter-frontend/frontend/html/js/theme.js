// ════════════════════════════════════════════════════════
//  theme.js — Modo oscuro para páginas que NO usan sidebar.js
//  (admin.html, superadmin.html). Usa la misma clave 'gi_theme'
//  que sidebar.js, así el tema elegido se mantiene consistente
//  en TODO el sistema, sin importar en qué página lo cambies.
//  Uso: <script src="./js/theme.js"></script>
//       llamar initThemeToggle() en DOMContentLoaded
// ════════════════════════════════════════════════════════

// Aplicar el tema guardado ANTES de pintar la página (evita parpadeo)
(function aplicarTemaGuardado() {
  if (localStorage.getItem('gi_theme') === 'dark') {
    document.documentElement.classList.add('dark-mode');
  }
})();

(function inyectarEstilosTema() {
  if (document.getElementById('theme-styles')) return;
  const style = document.createElement('style');
  style.id = 'theme-styles';
  style.textContent = `
    html.dark-mode #main {
      filter: invert(93%) hue-rotate(180deg) contrast(115%);
      background: #f4f7fb;
    }
    html.dark-mode #main img,
    html.dark-mode #main .leaflet-container,
    html.dark-mode #main video,
    html.dark-mode #main iframe {
      filter: invert(93%) hue-rotate(180deg) contrast(115%);
    }
    .gi-theme-btn {
      background: none; border: none; color: inherit;
      font-size: 1.1rem; cursor: pointer; padding: 6px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      transition: background .15s;
    }
    .gi-theme-btn:hover { background: rgba(0,0,0,.06); }
  `;
  document.head.appendChild(style);
})();

function _actualizarIconoTema() {
  const icono = document.getElementById('iconoTema');
  if (!icono) return;
  const esOscuro = document.documentElement.classList.contains('dark-mode');
  icono.className = esOscuro ? 'bi bi-sun' : 'bi bi-moon-stars';
}

function initThemeToggle() {
  const btn = document.getElementById('btnTema');
  if (!btn) return;
  _actualizarIconoTema();
  btn.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('gi_theme', document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
    _actualizarIconoTema();
  });
}
