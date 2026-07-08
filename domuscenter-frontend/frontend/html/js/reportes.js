// frontend/js/reportes.js
exigirSesion();

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

const COLORES = ['#dc2626','#fb923c','#d97706','#a3e635','#34d399','#38bdf8','#818cf8','#e879f9'];
const chartOpts = (type) => ({
  responsive: true, maintainAspectRatio: true,
  plugins: { legend: { labels: { color:'#94a3b8', font:{ size:11 } } } },
  scales: type==='pie' || type==='doughnut' ? {} : {
    x: { ticks:{ color:'#64748b' }, grid:{ color:'#e2e8f0' } },
    y: { ticks:{ color:'#64748b' }, grid:{ color:'#e2e8f0' }, beginAtZero:true },
  }
});

function renderChartEstado(datos) {
  const ctx = document.getElementById('chartEstado');
  if (chartEstado) chartEstado.destroy();
  chartEstado = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: datos.map(d=>d.estado),
      datasets:[{ data: datos.map(d=>d.total),
        backgroundColor: ['rgba(239,68,68,.7)','rgba(245,158,11,.7)','rgba(34,197,94,.7)','rgba(148,163,184,.7)'],
        borderColor: '#ffffff', borderWidth:2 }]
    },
    options: { ...chartOpts('doughnut'), cutout:'65%' }
  });
}

function renderChartCategoria(datos) {
  const ctx = document.getElementById('chartCategoria');
  if (chartCategoria) chartCategoria.destroy();
  chartCategoria = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: datos.map(d=>d.categoria),
      datasets:[{ label:'Incidencias', data: datos.map(d=>d.total),
        backgroundColor: COLORES.map(c=>c+'aa'), borderColor: COLORES, borderWidth:1, borderRadius:4 }]
    },
    options: { ...chartOpts('bar'), indexAxis:'y' }
  });
}

function renderChartPrioridad(datos) {
  const ctx = document.getElementById('chartPrioridad');
  if (chartPrioridad) chartPrioridad.destroy();
  const colPrio = { 'Alta':'#ef4444','Media':'#eab308','Baja':'#22c55e' };
  chartPrioridad = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: datos.map(d=>d.prioridad),
      datasets:[{ data: datos.map(d=>d.total),
        backgroundColor: datos.map(d=>(colPrio[d.prioridad]||'#64748b')+'aa'),
        borderColor: datos.map(d=>colPrio[d.prioridad]||'#64748b'), borderWidth:2 }]
    },
    options: { ...chartOpts('doughnut'), cutout:'65%' }
  });
}

function renderChartSucursal(datos) {
  const ctx = document.getElementById('chartSucursal');
  if (chartSucursal) chartSucursal.destroy();
  chartSucursal = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: datos.map(d=>d.sucursal),
      datasets:[
        { label:'Total', data: datos.map(d=>d.total),
          backgroundColor:'rgba(56,189,248,.6)', borderColor:'#38bdf8', borderWidth:1, borderRadius:4 },
        { label:'Críticas (Alta)', data: datos.map(d=>d.criticas),
          backgroundColor:'rgba(239,68,68,.7)', borderColor:'#ef4444', borderWidth:1, borderRadius:4 },
      ]
    },
    options: chartOpts('bar')
  });
}

function renderChartTendencia(datos) {
  const ctx = document.getElementById('chartTendencia');
  if (chartTendencia) chartTendencia.destroy();
  chartTendencia = new Chart(ctx, {
    type: 'line',
    data: {
      labels: datos.map(d=>d.mes),
      datasets:[{
        label:'Incidencias registradas', data: datos.map(d=>d.total),
        borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,.1)',
        fill:true, tension:.4, pointBackgroundColor:'#ef4444', pointRadius:4
      }]
    },
    options: chartOpts('line')
  });
}

function renderTablaResponsables(datos) {
  const html = datos.map(r => {
    const tasa = r.asignadas > 0 ? Math.round((r.resueltas/r.asignadas)*100) : 0;
    return `
      <tr style="border-color:#e2e8f0;">
        <td class="border-secondary small fw-semibold">${r.responsable}</td>
        <td class="border-secondary small text-center">${r.asignadas}</td>
        <td class="border-secondary small text-center text-success">${r.resueltas}</td>
        <td class="border-secondary small text-center text-warning">${r.en_proceso}</td>
        <td class="border-secondary small text-center">${tasa}%</td>
        <td class="border-secondary" style="min-width:120px;">
          <div class="progress" style="height:6px;background:#f4f7fb;">
            <div class="progress-bar bg-danger" style="width:${tasa}%;border-radius:4px;"></div>
          </div>
        </td>
      </tr>`;
  }).join('');
  document.getElementById('tbodyResponsables').innerHTML =
    html || '<tr><td colspan="6" class="text-center text-secondary py-4">Sin datos</td></tr>';
}

//EXPORTAR PDF
async function exportarPDF() {
  try {
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

    const r = await fetchAPI(`${API}/reportes/exportar-pdf?${params}`);
    if (!r.ok) throw new Error();
    const blob = await r.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `reporte-geoincidencias-${new Date().toISOString().split('T')[0]}.pdf`; a.click();
  } catch(e) { alert('Error al exportar el reporte.'); }
}

async function exportarCSV() {
  try {
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

    const r = await fetchAPI(`${API}/reportes/exportar-csv?${params}`);
    if (!r.ok) throw new Error();
    const blob = await r.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `reporte-geoincidencias-${new Date().toISOString().split('T')[0]}.csv`; a.click();
  } catch(e) { alert('Error al exportar el CSV.'); }
}

// ── Init ──
inicializarBarraUsuario();
poblarSelect(`${API}/catalogos/tipos`, 'rTipo');
poblarSelect(`${API}/catalogos/zonas`, 'rZona');
poblarSelect(`${API}/catalogos/sucursales`, 'rSucursal');
aplicarPeriodoRapido();
