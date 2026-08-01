// frontend/js/reportes.js
exigirAdmin(); // Reportes exclusivo de Admin/Superadmin (HU-07)

function mostrarAlerta(msg, tipo = 'success') {
  const el = document.getElementById('alerta');
  if (!el) return;
  el.innerHTML = `
    <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  el.style.display = 'block';
  setTimeout(() => { el.innerHTML = ''; el.style.display = 'none'; }, 4000);
}

let chartEstado, chartCategoria, chartPrioridad, chartTendencia, chartSucursal;

// ── Período rápido ──
function aplicarPeriodoRapido() {
  const dias = document.getElementById('periodoRapido').value;
  if (!dias) return;
  const hasta = new Date();
  const desde = new Date();
  desde.setDate(desde.getDate() - parseInt(dias));
  document.getElementById('rDesde').value = desde.toISOString().split('T')[0];
  document.getElementById('rHasta').value = hasta.toISOString().split('T')[0];
  cargarReportes();
}

// ── Poblar selects ──
async function poblarSelect(url, selectId) {
  try {
    const r = await fetchAPI(url);
    const datos = await r.json();
    const sel = document.getElementById(selectId);
    datos.forEach(d => {
      const opt = document.createElement('option');
      opt.value=d.id; opt.textContent=d.nombre; sel.appendChild(opt);
    });
  } catch(e) {}
}

// ── Cargar todos los reportes ──
async function cargarReportes() {
  const params = new URLSearchParams();
  const desde = document.getElementById('rDesde').value;
  const hasta = document.getElementById('rHasta').value;
  const tipo  = document.getElementById('rTipo').value;
  const zona  = document.getElementById('rZona').value;
  const sucursal = document.getElementById('rSucursal').value;
  if (desde) params.append('desde', desde);
  if (hasta) params.append('hasta', hasta);
  if (tipo)  params.append('tipo',  tipo);
  if (zona)  params.append('zona',  zona);
  if (sucursal) params.append('sucursal', sucursal);

  try {
    const [resumen, porTipo, porEstado, porSucursal, tendencia, porResponsable] = await Promise.all([
      fetchAPI(`${API}/reportes/resumen?${params}`).then(r=>r.json()),
      fetchAPI(`${API}/reportes/por-categoria?${params}`).then(r=>r.json()),
      fetchAPI(`${API}/reportes/por-estado?${params}`).then(r=>r.json()),
      fetchAPI(`${API}/reportes/por-sucursal?${params}`).then(r=>r.json()),
      fetchAPI(`${API}/reportes/tendencia?${params}`).then(r=>r.json()),
      fetchAPI(`${API}/reportes/por-responsable?${params}`).then(r=>r.json()),
    ]);
    renderKPIs(resumen);
    renderChartEstado(porEstado);
    renderChartCategoria(porTipo);
    renderChartPrioridad(resumen.por_prioridad || []);
    renderChartSucursal(porSucursal);
    renderChartTendencia(tendencia);
    renderTablaResponsables(porResponsable);
  } catch(e) { console.error('Error reportes:', e); }
}

function renderKPIs(d) {
  document.getElementById('kpiTotal').textContent          = d.total || 0;
  document.getElementById('kpiTiempoPromedio').textContent = d.dias_promedio ? parseFloat(d.dias_promedio).toFixed(1) : '—';
  document.getElementById('kpiTasaResolucion').textContent = d.total > 0 ? `${((d.resueltas/d.total)*100).toFixed(0)}%` : '—%';
  document.getElementById('kpiCriticas').textContent       = d.criticas || 0;
}


const COLORES = ['#0d9488','#dc2626','#f59e0b','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];

function _pct(parte, total) {
  if (!total || total <= 0) return 0;
  return Math.round((parte / total) * 1000) / 10; // 1 decimal
}

function _labelsConPct(items, labelKey, valueKey = 'total') {
  const sum = items.reduce((s, d) => s + Number(d[valueKey] || 0), 0);
  return items.map(d => {
    const n = Number(d[valueKey] || 0);
    return `${d[labelKey]}  ${_pct(n, sum)}%`;
  });
}

function _tooltipPct(items, valueKey = 'total') {
  const sum = items.reduce((s, d) => s + Number(d[valueKey] || 0), 0);
  return {
    callbacks: {
      label(ctx) {
        const n = Number(ctx.raw || 0);
        return ` ${ctx.label?.split('  ')[0] || ''}: ${n}  (${_pct(n, sum)}%)`;
      }
    }
  };
}

function renderChartEstado(datos) {
  const ctx = document.getElementById('chartEstado');
  if (!ctx) return;
  if (chartEstado) chartEstado.destroy();
  const sum = datos.reduce((s, d) => s + Number(d.total || 0), 0);
  const colores = {
    'Pendiente': '#f59e0b',
    'En proceso': '#3b82f6',
    'Resuelto': '#10b981',
    'Cerrado': '#64748b',
    'Rechazado': '#ef4444',
  };
  chartEstado = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: _labelsConPct(datos, 'estado'),
      datasets: [{
        data: datos.map(d => Number(d.total || 0)),
        backgroundColor: datos.map(d => colores[d.estado] || '#94a3b8'),
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '62%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#475569', font: { size: 11, family: 'Inter' }, boxWidth: 12, padding: 12 }
        },
        tooltip: _tooltipPct(datos),
        title: {
          display: true,
          text: sum ? `Total: ${sum} incidencias` : 'Sin datos',
          color: '#0b2340',
          font: { size: 13, weight: '600' }
        }
      }
    }
  });
}

function renderChartCategoria(datos) {
  const ctx = document.getElementById('chartCategoria');
  if (!ctx) return;
  if (chartCategoria) chartCategoria.destroy();
  const sum = datos.reduce((s, d) => s + Number(d.total || 0), 0);
  const tienePrioridad = datos.some(d => d.alta != null || d.media != null);
  const labels = datos.map(d => {
    const n = Number(d.total || 0);
    return `${d.categoria} (${_pct(n, sum)}%)`;
  });

  const datasets = tienePrioridad ? [
    { label: 'Alta', data: datos.map(d => Number(d.alta || 0)), backgroundColor: '#ef4444', borderRadius: 4, stack: 'p' },
    { label: 'Media', data: datos.map(d => Number(d.media || 0)), backgroundColor: '#f59e0b', borderRadius: 4, stack: 'p' },
    { label: 'Baja', data: datos.map(d => Number(d.baja || 0)), backgroundColor: '#10b981', borderRadius: 4, stack: 'p' },
  ] : [{
    label: 'Incidencias',
    data: datos.map(d => Number(d.total || 0)),
    backgroundColor: COLORES.map(c => c + 'cc'),
    borderRadius: 6,
  }];

  chartCategoria = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: tienePrioridad,
          position: 'top',
          labels: { color: '#475569', font: { size: 11 }, boxWidth: 12 }
        },
        tooltip: {
          callbacks: {
            afterBody(items) {
              if (!tienePrioridad || !items.length) return '';
              const i = items[0].dataIndex;
              const tot = Number(datos[i].total || 0);
              return `Total: ${tot}  (${_pct(tot, sum)}% del período)`;
            }
          }
        },
        title: {
          display: true,
          text: sum ? `${sum} en ${datos.length} categorías` : 'Sin datos',
          color: '#0b2340',
          font: { size: 13, weight: '600' }
        }
      },
      scales: {
        x: {
          stacked: tienePrioridad,
          beginAtZero: true,
          ticks: { color: '#64748b', precision: 0 },
          grid: { color: '#e2e8f0' },
          title: { display: true, text: 'Cantidad', color: '#94a3b8', font: { size: 11 } }
        },
        y: {
          stacked: tienePrioridad,
          ticks: { color: '#334155', font: { size: 11 } },
          grid: { display: false }
        }
      }
    }
  });
}

function renderChartPrioridad(datos) {
  const ctx = document.getElementById('chartPrioridad');
  if (!ctx) return;
  if (chartPrioridad) chartPrioridad.destroy();
  const sum = datos.reduce((s, d) => s + Number(d.total || 0), 0);
  const colPrio = { 'Alta': '#ef4444', 'Media': '#f59e0b', 'Baja': '#10b981' };
  chartPrioridad = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: _labelsConPct(datos, 'prioridad'),
      datasets: [{
        data: datos.map(d => Number(d.total || 0)),
        backgroundColor: datos.map(d => colPrio[d.prioridad] || '#64748b'),
        borderColor: '#fff',
        borderWidth: 2,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '62%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#475569', font: { size: 11 }, boxWidth: 12, padding: 12 }
        },
        tooltip: _tooltipPct(datos),
        title: {
          display: true,
          text: sum ? `Total: ${sum}` : 'Sin datos',
          color: '#0b2340',
          font: { size: 13, weight: '600' }
        }
      }
    }
  });
}

function renderChartSucursal(datos) {
  const ctx = document.getElementById('chartSucursal');
  if (!ctx) return;
  if (chartSucursal) chartSucursal.destroy();
  const sum = datos.reduce((s, d) => s + Number(d.total || 0), 0);
  const labels = datos.map(d => {
    const n = Number(d.total || 0);
    return `${d.sucursal} (${_pct(n, sum)}%)`;
  });
  const tieneDesglose = datos.some(d => d.alta != null || d.media != null || d.baja != null);

  chartSucursal = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: tieneDesglose ? [
        { label: 'Alta', data: datos.map(d => Number(d.alta || d.criticas || 0)), backgroundColor: '#ef4444', borderRadius: 4, stack: 'prio' },
        { label: 'Media', data: datos.map(d => Number(d.media || 0)), backgroundColor: '#f59e0b', borderRadius: 4, stack: 'prio' },
        { label: 'Baja', data: datos.map(d => Number(d.baja || 0)), backgroundColor: '#10b981', borderRadius: 4, stack: 'prio' },
      ] : [
        { label: 'Total', data: datos.map(d => Number(d.total || 0)), backgroundColor: '#0d9488cc', borderRadius: 6 },
        { label: 'Críticas (Alta)', data: datos.map(d => Number(d.criticas || 0)), backgroundColor: '#ef4444aa', borderRadius: 6 },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'top',
          labels: { color: '#475569', font: { size: 11 }, boxWidth: 12 }
        },
        tooltip: {
          callbacks: {
            afterBody(items) {
              if (!items.length) return '';
              const i = items[0].dataIndex;
              const tot = Number(datos[i].total || 0);
              return `Total sucursal: ${tot}  (${_pct(tot, sum)}% del período)`;
            }
          }
        },
        title: {
          display: true,
          text: sum ? `${sum} incidencias en ${datos.length} sucursales` : 'Sin datos',
          color: '#0b2340',
          font: { size: 13, weight: '600' }
        }
      },
      scales: {
        x: {
          stacked: tieneDesglose,
          ticks: { color: '#334155', font: { size: 11 }, maxRotation: 35, minRotation: 0 },
          grid: { display: false }
        },
        y: {
          stacked: tieneDesglose,
          beginAtZero: true,
          ticks: { color: '#64748b', precision: 0 },
          grid: { color: '#e2e8f0' },
          title: { display: true, text: 'Cantidad de incidencias', color: '#94a3b8', font: { size: 11 } }
        }
      }
    }
  });
}

function renderChartTendencia(datos) {
  const ctx = document.getElementById('chartTendencia');
  if (!ctx) return;
  if (chartTendencia) chartTendencia.destroy();
  chartTendencia = new Chart(ctx, {
    type: 'line',
    data: {
      labels: datos.map(d => d.mes),
      datasets: [{
        label: 'Incidencias',
        data: datos.map(d => Number(d.total || 0)),
        borderColor: '#0d9488',
        backgroundColor: 'rgba(13,148,136,.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: '#0d9488',
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) { return ` ${ctx.parsed.y} incidencia(s)`; }
          }
        },
        title: {
          display: true,
          text: 'Evolución mensual',
          color: '#0b2340',
          font: { size: 13, weight: '600' }
        }
      },
      scales: {
        x: { ticks: { color: '#64748b' }, grid: { color: '#f1f5f9' } },
        y: { beginAtZero: true, ticks: { color: '#64748b', precision: 0 }, grid: { color: '#e2e8f0' } }
      }
    }
  });
}


// ── Init ──
inicializarBarraUsuario();
poblarSelect(`${API}/catalogos/tipos`, 'rTipo');
poblarSelect(`${API}/catalogos/zonas`, 'rZona');

(function ajustarFiltrosPorRol() {
  const u = (typeof getUsuario === 'function') ? getUsuario() : JSON.parse(localStorage.getItem('gi_usuario') || '{}');
  const esSuper = u && u.rol === 'superadmin';
  // Admin de una sola sucursal: sin filtro de sucursal ni gráfica multi-sede
  if (!esSuper) {
    const wrap = document.getElementById('wrapFiltroSucursal');
    if (wrap) wrap.style.display = 'none';
    const sel = document.getElementById('rSucursal');
    if (sel) sel.value = '';
    // Ocultar bloque de gráfica por sucursal
    const canvas = document.getElementById('chartSucursal');
    if (canvas) {
      const card = canvas.closest('.rounded-3, .card, .col-12, .col-lg-12, .col-lg-6') || canvas.parentElement;
      if (card) card.style.display = 'none';
    }
    const sec = document.getElementById('seccionPorSucursal');
    if (sec) {
      const parent = sec.closest('.rounded-3, .card') || sec.parentElement;
      if (parent) parent.style.display = 'none';
    }
  } else {
    poblarSelect(`${API}/catalogos/sucursales`, 'rSucursal');
  }
})();

aplicarPeriodoRapido();
