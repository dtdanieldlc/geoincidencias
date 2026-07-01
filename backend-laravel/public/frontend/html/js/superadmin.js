/* ═══════════════════════════════════════════════════════════
   superadmin.js — Panel de SuperAdmin · GeoIncidencias
   Depende de: Bootstrap 5, auth-guard.js
═══════════════════════════════════════════════════════════ */

const token   = () => localStorage.getItem('gi_token') ?? '';
const headers = () => ({ 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` });

const MODULOS_DISPONIBLES = [
  { id: 'dashboard',   label: 'Dashboard'   },
  { id: 'incidencias', label: 'Incidencias' },
  { id: 'usuarios',    label: 'Usuarios'    },
  { id: 'incentivos',  label: 'Incentivos'  },
  { id: 'apoyos',      label: 'Apoyos'      },
  { id: 'reportes',    label: 'Reportes'    },
  { id: 'historial',   label: 'Historial'   },
];

let modalRevisar;
let usuarioAsignarActual = null;

/* ══════════════════════════════════════════════════════════
   INICIALIZACIÓN
══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  exigirSuperAdmin();
  initUsuarioActual();

  modalRevisar = new bootstrap.Modal(document.getElementById('modalRevisarSolicitud'));

  document.getElementById('tabSolicitudesBtn').addEventListener('click', () => cambiarTabSuperAdmin('solicitudes'));
  document.getElementById('tabAsignarBtn').addEventListener('click',     () => cambiarTabSuperAdmin('asignar'));

  document.getElementById('selectUsuarioAsignar').addEventListener('change', onCambiarUsuarioAsignar);
  document.getElementById('btnGuardarAsignacion').addEventListener('click', guardarAsignacion);

  document.getElementById('btnAprobarSolicitud').addEventListener('click',  () => resolverSolicitud('aprobado'));
  document.getElementById('btnRechazarSolicitud').addEventListener('click', () => resolverSolicitud('rechazado'));

  document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
  });

  cargarStats();
  cargarSolicitudesPendientes();
  cargarUsuariosAsignar();
  construirFilasModulos('tbodyModulosAsignar');
});

function initUsuarioActual() {
  const u = JSON.parse(localStorage.getItem('gi_usuario') ?? '{}');
  if (u.nombre) {
    document.getElementById('sideNombre').textContent = u.nombre;
    document.getElementById('sideRol').textContent    = 'Superadmin';
    document.getElementById('sideAvatar').textContent = u.nombre.charAt(0).toUpperCase();
  }
  document.getElementById('btnCerrarSesion').addEventListener('click', async () => {
    await fetch(`${API}/auth/logout`, { method: 'POST', headers: headers() });
    cerrarSesion();
  });
}

function construirFilasModulos(tbodyId) {
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;
  tbody.innerHTML = MODULOS_DISPONIBLES.map(m => `
    <tr>
      <td>${m.label}</td>
      <td class="text-center"><input type="checkbox" class="form-check-input mv" data-modulo="${m.id}"></td>
      <td class="text-center"><input type="checkbox" class="form-check-input me" data-modulo="${m.id}"></td>
      <td class="text-center"><input type="checkbox" class="form-check-input md" data-modulo="${m.id}"></td>
    </tr>
  `).join('');
}

function cambiarTabSuperAdmin(tab) {
  document.getElementById('panelSolicitudes').style.display = tab === 'solicitudes' ? 'block' : 'none';
  document.getElementById('panelAsignar').style.display     = tab === 'asignar'     ? 'block' : 'none';
  document.getElementById('tabSolicitudesBtn').classList.toggle('active', tab === 'solicitudes');
  document.getElementById('tabAsignarBtn').classList.toggle('active', tab === 'asignar');

  const titulo = document.getElementById('topbarTitle');
  titulo.textContent = tab === 'solicitudes' ? 'Solicitudes pendientes' : 'Asignar permisos directo';
}

/* ══════════════════════════════════════════════════════════
   STATS
══════════════════════════════════════════════════════════ */
async function cargarStats() {
  try {
    const [pend, apr, rech, admins] = await Promise.all([
      fetch(`${API}/superadmin/solicitudes-permisos?estado=pendiente`, { headers: headers() }).then(r => r.json()),
      fetch(`${API}/superadmin/solicitudes-permisos?estado=aprobado`,  { headers: headers() }).then(r => r.json()),
      fetch(`${API}/superadmin/solicitudes-permisos?estado=rechazado`,{ headers: headers() }).then(r => r.json()),
      fetch(`${API}/admin/usuarios?rol=admin&por_pagina=1`,           { headers: headers() }).then(r => r.json()),
    ]);
    document.getElementById('spStatPendientes').textContent  = pend.data?.total ?? '0';
    document.getElementById('spStatAprobadas').textContent   = apr.data?.total ?? '0';
    document.getElementById('spStatRechazadas').textContent  = rech.data?.total ?? '0';
    document.getElementById('spStatAdmins').textContent      = admins.data?.total ?? '0';
  } catch (e) { /* silencioso */ }
}

/* ══════════════════════════════════════════════════════════
   SOLICITUDES PENDIENTES
══════════════════════════════════════════════════════════ */
async function cargarSolicitudesPendientes() {
  const tbody = document.getElementById('tbodySolicitudesPendientes');
  try {
    const r = await fetch(`${API}/superadmin/solicitudes-permisos?estado=pendiente`, { headers: headers() });
    const data = await r.json();
    const lista = data.data?.data ?? [];

    if (lista.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5" style="color:var(--text-muted)">No hay solicitudes pendientes.</td></tr>';
      return;
    }

    tbody.innerHTML = lista.map(s => `
      <tr>
        <td>${s.solicitante ? `${s.solicitante.nombre} ${s.solicitante.apellido || ''}` : '—'}</td>
        <td>${s.usuarioObjetivo ? `${s.usuarioObjetivo.nombre} ${s.usuarioObjetivo.apellido || ''}` : '—'}</td>
        <td>${(s.permisos_solicitados || []).map(p => p.modulo).join(', ')}</td>
        <td style="max-width:220px; white-space:normal;">${s.motivo}</td>
        <td>${new Date(s.created_at).toLocaleDateString('es-EC')}</td>
        <td><button class="btn-icon" onclick="abrirModalRevisar(${s.id})"><i class="bi bi-eye"></i> Revisar</button></td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Error al cargar solicitudes.</td></tr>';
  }
}

async function abrirModalRevisar(id) {
  try {
    const r = await fetch(`${API}/superadmin/solicitudes-permisos/${id}`, { headers: headers() });
    const data = await r.json();
    const s = data.data;

    document.getElementById('revisarSolicitudId').value = s.id;
    document.getElementById('revisarSolicitante').textContent = s.solicitante ? `${s.solicitante.nombre} ${s.solicitante.apellido || ''} (${s.solicitante.correo})` : '—';
    document.getElementById('revisarObjetivo').textContent    = s.usuarioObjetivo ? `${s.usuarioObjetivo.nombre} ${s.usuarioObjetivo.apellido || ''} (${s.usuarioObjetivo.correo})` : '—';
    document.getElementById('revisarMotivo').textContent      = s.motivo;
    document.getElementById('revisarRespuesta').value = '';

    const tbody = document.getElementById('tbodyModulosRevisar');
    const solicitadosPorModulo = {};
    (s.permisos_solicitados || []).forEach(p => { solicitadosPorModulo[p.modulo] = p; });

    tbody.innerHTML = MODULOS_DISPONIBLES.map(m => {
      const p = solicitadosPorModulo[m.id] || { puede_ver: false, puede_editar: false, puede_eliminar: false };
      return `
        <tr>
          <td class="small">${m.label}</td>
          <td class="text-center"><input type="checkbox" class="form-check-input rv" data-modulo="${m.id}" ${p.puede_ver ? 'checked' : ''}></td>
          <td class="text-center"><input type="checkbox" class="form-check-input re" data-modulo="${m.id}" ${p.puede_editar ? 'checked' : ''}></td>
          <td class="text-center"><input type="checkbox" class="form-check-input rd" data-modulo="${m.id}" ${p.puede_eliminar ? 'checked' : ''}></td>
        </tr>
      `;
    }).join('');

    modalRevisar.show();
  } catch (e) {
    mostrarToast('Error al cargar la solicitud.', 'danger');
  }
}

async function resolverSolicitud(decision) {
  const id = document.getElementById('revisarSolicitudId').value;
  const respuesta = document.getElementById('revisarRespuesta').value.trim();

  const permisos_aprobados = MODULOS_DISPONIBLES.map(m => ({
    modulo: m.id,
    puede_ver:      document.querySelector(`.rv[data-modulo="${m.id}"]`)?.checked || false,
    puede_editar:   document.querySelector(`.re[data-modulo="${m.id}"]`)?.checked || false,
    puede_eliminar: document.querySelector(`.rd[data-modulo="${m.id}"]`)?.checked || false,
  }));

  try {
    const r = await fetch(`${API}/superadmin/solicitudes-permisos/${id}/revisar`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({
        decision: decision === 'aprobado' ? 'aprobado' : 'rechazado',
        respuesta: respuesta || null,
        permisos_aprobados: decision === 'aprobado' ? permisos_aprobados : undefined,
      }),
    });
    const data = await r.json();
    if (data.ok) {
      mostrarToast(data.mensaje, 'success');
      modalRevisar.hide();
      cargarSolicitudesPendientes();
      cargarStats();
    } else {
      mostrarToast(data.mensaje || 'No se pudo procesar la solicitud.', 'danger');
    }
  } catch (e) {
    mostrarToast('Error de conexión.', 'danger');
  }
}

/* ══════════════════════════════════════════════════════════
   ASIGNAR PERMISOS DIRECTO
══════════════════════════════════════════════════════════ */
async function cargarUsuariosAsignar() {
  const select = document.getElementById('selectUsuarioAsignar');
  try {
    const r = await fetch(`${API}/admin/usuarios?rol=admin&por_pagina=100`, { headers: headers() });
    const data = await r.json();
    const usuarios = data.data?.data ?? [];
    select.innerHTML = '<option value="">Selecciona un usuario…</option>' +
      usuarios.map(u => `<option value="${u.id_usuario}">${u.nombre} ${u.apellido || ''} — ${u.correo}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Error al cargar usuarios</option>';
  }
}

async function onCambiarUsuarioAsignar() {
  const idUsuario = document.getElementById('selectUsuarioAsignar').value;
  usuarioAsignarActual = idUsuario || null;

  const tbodyActuales = document.getElementById('tbodyPermisosActuales');
  document.querySelectorAll('#tbodyModulosAsignar .mv, #tbodyModulosAsignar .me, #tbodyModulosAsignar .md').forEach(c => c.checked = false);

  if (!idUsuario) {
    tbodyActuales.innerHTML = '<tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)">Selecciona un usuario</td></tr>';
    return;
  }

  try {
    const r = await fetch(`${API}/superadmin/permisos/${idUsuario}`, { headers: headers() });
    const data = await r.json();
    const permisos = data.permisos ?? [];

    tbodyActuales.innerHTML = permisos.map(p => `
      <tr>
        <td class="small">${MODULOS_DISPONIBLES.find(m => m.id === p.modulo)?.label ?? p.modulo}</td>
        <td class="text-center">${p.puede_ver ? '✅' : '—'}</td>
        <td class="text-center">${p.puede_editar ? '✅' : '—'}</td>
        <td class="text-center">${p.puede_eliminar ? '✅' : '—'}</td>
      </tr>
    `).join('');

    // Precargar checkboxes editables con los valores actuales
    permisos.forEach(p => {
      const v = document.querySelector(`#tbodyModulosAsignar .mv[data-modulo="${p.modulo}"]`);
      const e = document.querySelector(`#tbodyModulosAsignar .me[data-modulo="${p.modulo}"]`);
      const d = document.querySelector(`#tbodyModulosAsignar .md[data-modulo="${p.modulo}"]`);
      if (v) v.checked = !!p.puede_ver;
      if (e) e.checked = !!p.puede_editar;
      if (d) d.checked = !!p.puede_eliminar;
    });
  } catch (e) {
    tbodyActuales.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error al cargar permisos.</td></tr>';
  }
}

async function guardarAsignacion() {
  const msgEl = document.getElementById('msgAsignarPermiso');
  if (!usuarioAsignarActual) {
    msgEl.className = 'alert alert-danger py-2 small mt-2';
    msgEl.textContent = 'Selecciona un usuario primero.';
    msgEl.style.display = 'block';
    return;
  }

  const permisos = MODULOS_DISPONIBLES.map(m => ({
    modulo: m.id,
    puede_ver:      document.querySelector(`#tbodyModulosAsignar .mv[data-modulo="${m.id}"]`)?.checked || false,
    puede_editar:   document.querySelector(`#tbodyModulosAsignar .me[data-modulo="${m.id}"]`)?.checked || false,
    puede_eliminar: document.querySelector(`#tbodyModulosAsignar .md[data-modulo="${m.id}"]`)?.checked || false,
  }));

  const btn = document.getElementById('btnGuardarAsignacion');
  btn.disabled = true;

  try {
    const r = await fetch(`${API}/superadmin/permisos/${usuarioAsignarActual}`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({ permisos }),
    });
    const data = await r.json();
    if (data.ok) {
      msgEl.className = 'alert alert-success py-2 small mt-2';
      msgEl.textContent = data.mensaje;
      msgEl.style.display = 'block';
      onCambiarUsuarioAsignar();
      cargarStats();
    } else {
      msgEl.className = 'alert alert-danger py-2 small mt-2';
      msgEl.textContent = data.mensaje || 'No se pudo guardar.';
      msgEl.style.display = 'block';
    }
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-2';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    setTimeout(() => { msgEl.style.display = 'none'; }, 5000);
  }
}

/* ══════════════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════════════ */
function mostrarToast(mensaje, tipo = 'success') {
  const toast = document.getElementById('toast');
  const colores = { success: '#0d3321', danger: '#3d1f1f' };
  const textos  = { success: '#3fb950', danger: '#f85149' };
  toast.style.background = colores[tipo] ?? colores.success;
  toast.style.color = textos[tipo] ?? textos.success;
  toast.style.border = `1px solid ${textos[tipo] ?? textos.success}`;
  toast.textContent = mensaje;
  toast.style.display = 'block';
  setTimeout(() => { toast.style.display = 'none'; }, 4000);
}
