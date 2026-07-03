// public/js/dashboard.js
exigirSesion();
// API ya está definida globalmente en auth-guard.js

const COLOR_ESTADO = {
  'Abierta':    { bg:'rgba(239,68,68,.15)',   color:'#f87171',  borde:'#ef4444' },
  'En proceso': { bg:'rgba(245,158,11,.15)',  color:'#fbbf24',  borde:'#f59e0b' },
  'Resuelta':   { bg:'rgba(34,197,94,.15)',   color:'#4ade80',  borde:'#22c55e' },
  'Cerrada':    { bg:'rgba(148,163,184,.15)', color:'#94a3b8',  borde:'#64748b' },
};
const COLOR_PRIO = {
  'Crítica': '#ef4444',
  'Alta':    '#f97316',
  'Media':   '#eab308',
  'Baja':    '#22c55e',
};

// ── Mapa ──
let mapa;
function iniciarMapa() {
  mapa = L.map('mapa', { zoomControl: true }).setView([-2.2200, -80.9100], 11);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap © CARTO', maxZoom: 19
  }).addTo(mapa);
}

// ── Pines fijos de las 4 sucursales, con conteo de incidencias ──
async function cargarSucursalesEnMapa() {
  try {
    const r = await fetchAPI(`${API}/dashboard/por-sucursal`);
    const datos = await r.json();
    if (!datos.length) return;

    const bounds = [];
    datos.forEach(s => {
      if (!s.latitud || !s.longitud) return;
      bounds.push([s.latitud, s.longitud]);

      const abiertas = Number(s.abiertas || 0);
      const colorPin = abiertas > 0 ? '#ef4444' : '#22c55e';

      const icono = L.divIcon({
        className: '',
        html: `
          <div style="position:relative; width:34px; height:34px;">
            <div style="width:34px;height:34px;border-radius:50% 50% 50% 0;background:${colorPin};transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-shop" style="transform:rotate(45deg); color:#fff; font-size:14px; margin:-2px 0 0 -2px;"></i>
            </div>
            ${abiertas > 0 ? `<div style="position:absolute; top:-6px; right:-6px; background:#fff; color:${colorPin}; border-radius:50%; width:18px; height:18px; font-size:.65rem; font-weight:700; display:flex; align-items:center; justify-content:center; border:1px solid ${colorPin};">${abiertas}</div>` : ''}
          </div>`,
        iconSize: [34, 34], iconAnchor: [17, 34],
      });

      L.marker([s.latitud, s.longitud], { icon: icono, zIndexOffset: 1000 })
        .addTo(mapa)
        .bindPopup(`
          <div style="min-width:170px;">
            <strong>${s.sucursal}</strong><br/>
            <span style="color:#94a3b8;">${s.total} incidencia${s.total == 1 ? '' : 's'} total${s.total == 1 ? '' : 'es'}</span><br/>
            <span style="color:${colorPin};">● ${abiertas} abierta${abiertas == 1 ? '' : 's'}</span>
          </div>`);
    });

    if (bounds.length) mapa.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
  } catch (e) { console.error('Error sucursales en mapa:', e); }
}

function badgeEstado(estado) {
  const c = COLOR_ESTADO[estado] || { bg:'rgba(148,163,184,.15)', color:'#94a3b8' };
  return `<span class="badge rounded-pill" style="background:${c.bg};color:${c.color};padding:5px 10px;font-size:.75rem;">${estado}</span>`;
}
function badgePrio(p) {
  const c = COLOR_PRIO[p] || '#94a3b8';
  return `<span class="badge" style="background:${c}20;color:${c};border:1px solid ${c}40;padding:4px 8px;font-size:.72rem;">${p}</span>`;
}

// ── Resumen tarjetas ──
async function cargarResumen() {
  try {
    const r = await fetchAPI(`${API}/dashboard/resumen`);
    const d = await r.json();
    const t = d.total || 0;
    document.getElementById('cntTotal').textContent = d.total || 0;
    document.getElementById('cntAbiertas').textContent  = d.abiertas    || 0;
    document.getElementById('cntEnProceso').textContent = d.en_proceso  || 0;
    document.getElementById('cntResueltas').textContent = d.resueltas   || 0;
    if (t > 0) {
      document.getElementById('pctAbiertas').textContent  = `${((d.abiertas/t)*100).toFixed(1)}% del total`;
      document.getElementById('pctEnProceso').textContent = `${((d.en_proceso/t)*100).toFixed(1)}% del total`;
      document.getElementById('pctResueltas').textContent = `${((d.resueltas/t)*100).toFixed(1)}% del total`;
    }
  } catch(e) { console.error('Error resumen:', e); }
}

// ── Marcadores en el mapa ──
function _jitter(seed) {
  // Pequeño desplazamiento determinístico (según el id) para que los puntos
  // de una misma sucursal no queden apilados exactamente en el mismo pixel.
  const x = Math.sin(seed) * 10000;
  const frac = x - Math.floor(x);
  return (frac - 0.5) * 0.006; // ~± 300m
}

async function cargarMarcadores() {
  try {
    const r    = await fetchAPI(`${API}/incidencias/mapa`);
    const datos = await r.json();
    datos.forEach(inc => {
      if (!inc.latitud || !inc.longitud) return;
      const c = COLOR_ESTADO[inc.estado] || { borde:'#94a3b8' };
      const lat = Number(inc.latitud) + _jitter(inc.id_incidencia || Math.random() * 1000);
      const lng = Number(inc.longitud) + _jitter((inc.id_incidencia || Math.random() * 1000) * 1.37);
      const ico = L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:${c.borde};border:2px solid white;box-shadow:0 0 6px ${c.borde};"></div>`,
        iconSize: [14,14], iconAnchor: [7,7]
      });
      L.marker([lat, lng], { icon: ico })
        .addTo(mapa)
        .bindPopup(`
          <div style="min-width:180px;">
            <strong>${inc.titulo}</strong><br/>
            <small class="text-muted">${inc.tipo} · ${inc.zona}</small><br/>
            <span style="color:${c.borde};">● ${inc.estado}</span>
          </div>`);
    });
  } catch(e) { console.error('Error marcadores:', e); }
}

// ── Por categoría ──
async function cargarPorCategoria() {
  try {
    const r    = await fetchAPI(`${API}/dashboard/por-tipo`);
    const datos = await r.json();
    const colores = ['#f87171','#fb923c','#fbbf24','#a3e635','#34d399','#38bdf8','#818cf8','#e879f9'];
    const max  = datos.reduce((m,d) => Math.max(m, d.total), 0);
    const html = datos.map((d,i) => {
      const c   = colores[i % colores.length];
      const pct = max > 0 ? Math.round((d.total/max)*100) : 0;
      return `
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="small text-white">${d.tipo}</span>
          <strong class="small">${d.total}</strong>
        </div>
        <div class="progress mb-3" style="height:5px;background:#0d1117;">
          <div class="progress-bar" style="width:${pct}%;background:${c};border-radius:4px;"></div>
        </div>`;
    }).join('');
    document.getElementById('porCategoria').innerHTML = html || '<p class="text-secondary text-center small">Sin datos</p>';
  } catch(e) { document.getElementById('porCategoria').innerHTML = '<p class="text-danger text-center small">Error al cargar</p>'; }
}

// ── Últimas incidencias ──
async function cargarUltimas() {
  try {
    const r    = await fetchAPI(`${API}/dashboard/ultimas`);
    const datos = await r.json();
    const html = datos.map(inc => `
      <tr style="border-color:#21262d;">
        <td class="border-secondary py-3">
          <div class="fw-semibold small">${inc.titulo}</div>
          <div class="text-secondary" style="font-size:.78rem;">#${inc.id_incidencia}</div>
        </td>
        <td class="border-secondary small text-secondary">${inc.tipo}</td>
        <td class="border-secondary small text-secondary">${inc.zona}</td>
        <td class="border-secondary">${badgePrio(inc.prioridad)}</td>
        <td class="border-secondary">${badgeEstado(inc.estado)}</td>
        <td class="border-secondary small text-secondary">${new Date(inc.fecha_ocurrencia).toLocaleDateString('es-EC')}</td>
      </tr>`).join('');
    document.getElementById('tbodyUltimas').innerHTML = html || '<tr><td colspan="6" class="text-center text-secondary py-4">Sin incidencias recientes</td></tr>';
  } catch(e) {
    document.getElementById('tbodyUltimas').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar datos</td></tr>';
  }
}

// ── Init ──
inicializarBarraUsuario();
iniciarMapa();
cargarResumen();
cargarPorCategoria();
cargarUltimas();
cargarMarcadores();
cargarSucursalesEnMapa();
