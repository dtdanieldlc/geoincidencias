// frontend/js/registrar.js
exigirSesion();

let mapaReg, marcador;

// ── Mapa interactivo de registro ──
function iniciarMapa() {
  mapaReg = L.map('mapaRegistrar').setView([-2.9001, -79.0059], 13);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap © CARTO', maxZoom: 19
  }).addTo(mapaReg);

  mapaReg.on('click', (e) => {
    const { lat, lng } = e.latlng;
    document.getElementById('latitud').value  = lat.toFixed(6);
    document.getElementById('longitud').value = lng.toFixed(6);
    if (marcador) mapaReg.removeLayer(marcador);
    marcador = L.marker([lat, lng]).addTo(mapaReg).bindPopup('Incidencia aquí').openPopup();
  });
}

function mostrarAlerta(msg, tipo = 'success') {
  const el = document.getElementById('alerta');
  el.innerHTML = `
    <div class="alert alert-${tipo} alert-dismissible fade show shadow-lg border-0" role="alert">
      <i class="bi bi-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 5000);
}

async function poblarSelect(url, selectId) {
  try {
    const r    = await fetchAPI(url);
    const datos = await r.json();
    const sel  = document.getElementById(selectId);
    datos.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.id;
      opt.textContent = d.nombre;
      sel.appendChild(opt);
    });
  } catch(e) { console.error(`Error ${selectId}:`, e); }
}

// ── Cargar subtipos según el tipo elegido ──
async function cargarSubtipos() {
  const idTipo = document.getElementById('id_tipo').value;
  const selSubtipo = document.getElementById('id_subtipo');
  selSubtipo.innerHTML = '<option value="">Sin subtipo específico</option>';
  if (!idTipo) return;
  try {
    const r = await fetchAPI(`${API}/catalogos/subtipos/${idTipo}`);
    const datos = await r.json();
    datos.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.id; opt.textContent = d.nombre;
      selSubtipo.appendChild(opt);
    });
  } catch(e) {}
}

function marcarError(id) {
  document.getElementById(id).classList.add('is-invalid');
  document.getElementById(id).classList.remove('is-valid');
}
function marcarOk(id) {
  document.getElementById(id).classList.remove('is-invalid');
  document.getElementById(id).classList.add('is-valid');
}

// ── Guardar ──
async function guardarIncidencia() {
  const btn = document.getElementById('btnGuardar');

  ['titulo','id_tipo','prioridad','fecha_ocurrencia','id_zona'].forEach(id => {
    document.getElementById(id).classList.remove('is-invalid','is-valid');
  });

  let ok = true;
  const titulo    = document.getElementById('titulo').value.trim();
  const id_tipo   = document.getElementById('id_tipo').value;
  const id_subtipo= document.getElementById('id_subtipo').value;
  const prioridad = document.getElementById('prioridad').value;
  const fecha     = document.getElementById('fecha_ocurrencia').value;
  const zona      = document.getElementById('id_zona').value;
  const lat       = document.getElementById('latitud').value;
  const lng       = document.getElementById('longitud').value;

  if (!titulo)    { marcarError('titulo');    mostrarAlerta('El <strong>título</strong> es obligatorio.', 'warning'); ok=false; }
  if (!id_tipo)   { marcarError('id_tipo');   if(ok) mostrarAlerta('Selecciona el <strong>tipo</strong>.', 'warning'); ok=false; }
  if (!prioridad) { marcarError('prioridad'); if(ok) mostrarAlerta('Selecciona la <strong>prioridad</strong>.', 'warning'); ok=false; }
  if (!fecha)     { marcarError('fecha_ocurrencia'); if(ok) mostrarAlerta('La <strong>fecha de ocurrencia</strong> es obligatoria.', 'warning'); ok=false; }
  if (!zona)      { marcarError('id_zona');   if(ok) mostrarAlerta('Selecciona la <strong>zona</strong>.', 'warning'); ok=false; }
  if (!lat || !lng) { if(ok) mostrarAlerta('Haz clic en el mapa para marcar la <strong>ubicación</strong>.', 'warning'); ok=false; }

  if (!ok) {
    const primer = document.querySelector('.is-invalid');
    if (primer) primer.scrollIntoView({ behavior:'smooth', block:'center' });
    return;
  }

  marcarOk('titulo'); marcarOk('id_tipo'); marcarOk('prioridad'); marcarOk('fecha_ocurrencia'); marcarOk('id_zona');

  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2 spin"></i>Registrando…';

  const payload = {
    titulo,
    descripcion:         document.getElementById('descripcion').value.trim(),
    id_tipo:             parseInt(id_tipo),
    id_subtipo:          id_subtipo ? parseInt(id_subtipo) : null,
    prioridad,
    id_zona:             parseInt(zona),
    fecha_ocurrencia:    fecha,
    hora_ocurrencia:     document.getElementById('hora_ocurrencia').value || null,
    latitud:             parseFloat(lat),
    longitud:            parseFloat(lng),
    reportante_nombre:   document.getElementById('reportante_nombre').value.trim(),
    reportante_contacto: document.getElementById('reportante_contacto').value.trim(),
  };

  try {
    const res  = await fetchAPI(`${API}/incidencias`, { method:'POST', body: JSON.stringify(payload) });
    const data = await res.json();
    if (res.ok && data.ok) {
      mostrarAlerta('✅ Incidencia registrada y enviada a revisión del administrador.', 'success');
      setTimeout(() => window.location.href = 'incidencias.html', 2200);
    } else {
      mostrarAlerta(data.mensaje || 'Error al registrar.', 'danger');
    }
  } catch(e) {
    mostrarAlerta(e.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy2 me-2"></i>Registrar incidencia';
  }
}

// ── Init ──
inicializarBarraUsuario();
iniciarMapa();
poblarSelect(`${API}/catalogos/tipos`, 'id_tipo');
poblarSelect(`${API}/catalogos/zonas`, 'id_zona');
document.getElementById('fecha_ocurrencia').value = new Date().toISOString().split('T')[0];

// Prellenar datos del reportante con el usuario logueado
const uActual = getUsuario();
if (uActual) {
  const elNombre = document.getElementById('reportante_nombre');
  if (elNombre) elNombre.value = uActual.nombre;
}
