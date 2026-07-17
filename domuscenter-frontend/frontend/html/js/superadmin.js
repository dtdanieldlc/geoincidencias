/* ═══════════════════════════════════════════════════════════
   superadmin.js — Panel de SuperAdmin · DomusCenter
   Depende de: Bootstrap 5, auth-guard.js
═══════════════════════════════════════════════════════════ */

const token   = () => localStorage.getItem('gi_token') ?? '';
const headers = () => ({ 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` });

let _toastTimer;
function showToast(msg, type = 'success') {
  const colores = {
    success: { bg: '#e6f8ee', color: '#16a34a', border: '#2ea04326' },
    error:   { bg: '#fbe9e9', color: '#dc2626', border: '#dc262626' },
    warning: { bg: '#fef3e0', color: '#d97706', border: '#d9770626' },
  };
  const c = colores[type] ?? colores.success;
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  Object.assign(t.style, {
    display: 'block',
    background: c.bg,
    color: c.color,
    border: `1px solid ${c.border}`,
    top: '16px', right: '16px', position: 'fixed',
    minWidth: '280px', padding: '12px 16px',
    borderRadius: '8px', fontSize: '.85rem', zIndex: 9999,
  });
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
}

const MODULOS_DISPONIBLES = [
  { id: 'incidencias', label: 'Incidencias', acciones: ['ver', 'editar', 'eliminar'] },
  { id: 'usuarios',    label: 'Usuarios',    acciones: ['ver', 'editar'] },
  { id: 'incentivos',  label: 'Incentivos',  acciones: ['ver', 'editar'] },
  { id: 'historial',   label: 'Historial',   acciones: ['ver'] },
];

// Checkbox si el módulo realmente implementa esa acción en el backend, o un
// guion si no aplica (ej. "Eliminar" para Usuarios/Incentivos, "Editar"/
// "Eliminar" para Historial, que es solo un log de auditoría).
function _celdaPermiso(modulo, accion, claseCheckbox, checked = false) {
  if (!modulo.acciones.includes(accion)) {
    return `<span class="text-muted" style="opacity:.4;">—</span>`;
  }
  return `<input type="checkbox" class="form-check-input ${claseCheckbox}" data-modulo="${modulo.id}" ${checked ? 'checked' : ''}>`;
}

let modalRevisar;
let modalDetalle;
let modalEliminar;
let usuarioAEliminar = null;
let usuarioAsignarActual = null;

/* ══════════════════════════════════════════════════════════
   INICIALIZACIÓN
══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  exigirSuperAdmin();
  initSidebar('superadmin');

  modalRevisar = new bootstrap.Modal(document.getElementById('modalRevisarSolicitud'));

  document.getElementById('tabSolicitudesBtn').addEventListener('click', () => cambiarTabSuperAdmin('solicitudes'));
  document.getElementById('tabAsignarBtn').addEventListener('click',     () => cambiarTabSuperAdmin('asignar'));
  document.getElementById('tabDetalleBtn').addEventListener('click',     () => cambiarTabSuperAdmin('detalle'));
  document.getElementById('tabReportesUsuarioBtn').addEventListener('click', () => cambiarTabSuperAdmin('reportes-usuario'));
  document.getElementById('tabDenunciasBtn').addEventListener('click', () => cambiarTabSuperAdmin('denuncias'));

  document.querySelectorAll('.filtro-estado-denuncia').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-estado-denuncia').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      cargarDenuncias(btn.dataset.estado);
    });
  });
  document.getElementById('buscarDenunciaInput').addEventListener('input', filtrarDenuncias);
  _actualizarBadgeDenunciasPendientes();

  document.getElementById('btnGuardarDetalleUsuario').addEventListener('click', guardarDetalle);
  document.getElementById('btnResetPasswordDetalle').addEventListener('click', resetPasswordDetalle);
  document.getElementById('buscarDetalleInput').addEventListener('input', filtrarDetalleUsuarios);
  document.getElementById('buscarReporteUsuarioInput').addEventListener('input', filtrarReportesUsuario);

  document.getElementById('btnGuardarCrearUsuario').addEventListener('click', crearUsuario);
  document.getElementById('modalCrearUsuario').addEventListener('show.bs.modal', () => {
    ['crearNombre','crearApellido','crearCorreo','crearPassword','crearTelefono','crearCedula'].forEach(id => {
      document.getElementById(id).value = '';
    });
    document.getElementById('crearRol').value = 'usuario';
    document.getElementById('msgCrearUsuario').style.display = 'none';
  });

  modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminarUsuario'));
  document.getElementById('btnConfirmarEliminarUsuario').addEventListener('click', confirmarEliminarUsuario);

  document.getElementById('selectUsuarioAsignar').addEventListener('change', onCambiarUsuarioAsignar);
  document.getElementById('btnGuardarAsignacion').addEventListener('click', guardarAsignacion);

  document.getElementById('btnAprobarSolicitud').addEventListener('click',  () => resolverSolicitud('aprobado'));
  document.getElementById('btnRechazarSolicitud').addEventListener('click', () => resolverSolicitud('rechazado'));

  cargarStats();
  cargarSolicitudesPendientes();
  cargarUsuariosAsignar();
  construirFilasModulos('tbodyModulosAsignar');
});

function construirFilasModulos(tbodyId) {
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;
  tbody.innerHTML = MODULOS_DISPONIBLES.map(m => `
    <tr>
      <td>${m.label}</td>
      <td class="text-center">${_celdaPermiso(m, 'ver', 'mv')}</td>
      <td class="text-center">${_celdaPermiso(m, 'editar', 'me')}</td>
      <td class="text-center">${_celdaPermiso(m, 'eliminar', 'md')}</td>
    </tr>
  `).join('');
}

function cambiarTabSuperAdmin(tab) {
  document.getElementById('panelSolicitudes').style.display = tab === 'solicitudes' ? 'block' : 'none';
  document.getElementById('panelAsignar').style.display     = tab === 'asignar'     ? 'block' : 'none';
  document.getElementById('panelDetalle').style.display     = tab === 'detalle'     ? 'block' : 'none';
  document.getElementById('panelReportesUsuario').style.display = tab === 'reportes-usuario' ? 'block' : 'none';
  document.getElementById('panelDenuncias').style.display   = tab === 'denuncias'   ? 'block' : 'none';
  document.getElementById('tabSolicitudesBtn').classList.toggle('active', tab === 'solicitudes');
  document.getElementById('tabAsignarBtn').classList.toggle('active', tab === 'asignar');
  document.getElementById('tabDetalleBtn').classList.toggle('active', tab === 'detalle');
  document.getElementById('tabReportesUsuarioBtn').classList.toggle('active', tab === 'reportes-usuario');
  document.getElementById('tabDenunciasBtn').classList.toggle('active', tab === 'denuncias');

  const titulos = {
    solicitudes: 'Solicitudes pendientes',
    asignar: 'Asignar permisos directo',
    detalle: 'Detalle de Usuarios',
    'reportes-usuario': 'Reportes por Usuario',
    denuncias: 'Denuncias de Usuarios',
  };
  const tituloEl = document.querySelector('.gi-page-title');
  if (tituloEl) tituloEl.textContent = titulos[tab] || '';

  if (tab === 'detalle' && !_detalleUsuariosCargados) cargarDetalleUsuarios();
  if (tab === 'reportes-usuario' && !_reportesUsuarioCargados) cargarReportesUsuario();
  if (tab === 'denuncias') cargarDenuncias();
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
          <td class="text-center">${_celdaPermiso(m, 'ver', 'rv', p.puede_ver)}</td>
          <td class="text-center">${_celdaPermiso(m, 'editar', 're', p.puede_editar)}</td>
          <td class="text-center">${_celdaPermiso(m, 'eliminar', 'rd', p.puede_eliminar)}</td>
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
  const colores = { success: '#e6f8ee', danger: '#fbe9e9' };
  const textos  = { success: '#16a34a', danger: '#dc2626' };
  toast.style.background = colores[tipo] ?? colores.success;
  toast.style.color = textos[tipo] ?? textos.success;
  toast.style.border = `1px solid ${textos[tipo] ?? textos.success}`;
  toast.textContent = mensaje;
  toast.style.display = 'block';
  setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

/* ══════════════════════════════════════════════════════════
   DETALLE DE USUARIOS — ver/editar todo, incluida la
   pregunta secreta, y restablecer contraseña
══════════════════════════════════════════════════════════ */
let _detalleUsuariosCargados = false;
let _todosLosUsuariosDetalle = [];
let _reportesUsuarioCargados = false;
let _todosLosUsuariosReporte = [];

async function cargarDetalleUsuarios() {
  const tbody = document.getElementById('tbodyDetalleUsuarios');
  try {
    const r = await fetch(`${API}/superadmin/usuarios?por_pagina=200`, { headers: headers() });
    const data = await r.json();
    _todosLosUsuariosDetalle = data.data?.data ?? [];
    _detalleUsuariosCargados = true;
    _renderDetalleUsuarios(_todosLosUsuariosDetalle);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">Error al cargar usuarios.</td></tr>';
  }
}

function _renderDetalleUsuarios(lista) {
  const tbody = document.getElementById('tbodyDetalleUsuarios');
  if (lista.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-muted)">Sin resultados.</td></tr>';
    return;
  }
  const rolBadgeMap = {
    superadmin: '<span class="badge" style="background:#f3e8fd;color:#9333ea;">Superadmin</span>',
    admin:      '<span class="badge" style="background:#f3e8fd;color:#a78bfa;">Admin</span>',
    usuario:    '<span class="badge" style="background:#eef4f8;color:#64748b;">Usuario</span>',
  };
  tbody.innerHTML = lista.map((u, idx) => `
    <tr>
      <td class="small text-secondary" title="ID interno: ${u.id_usuario}">#${idx + 1}</td>
      <td>${u.nombre} ${u.apellido || ''}</td>
      <td class="small">${u.correo}</td>
      <td>${rolBadgeMap[u.rol] || u.rol}</td>
      <td class="small">${u.correo_verificado ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-secondary"></i>'}</td>
      <td>${u.activo
        ? '<span class="badge" style="background:#dcfce7;color:#16a34a;">Activo</span>'
        : '<span class="badge" style="background:#fee2e2;color:#dc2626;">Desactivado</span>'}</td>
      <td>
        ${u.rol === 'superadmin'
          ? '<span class="small" style="color:var(--text-muted)">No editable</span>'
          : `<button class="btn-icon" title="Ver / editar todo" onclick="abrirModalDetalle(${u.id_usuario})"><i class="bi bi-eye"></i> Ver detalle</button>
             <button class="btn-icon" title="${u.activo ? 'Desactivar' : 'Activar'} usuario" onclick="toggleActivoDetalle(${u.id_usuario}, ${u.activo})"><i class="bi bi-${u.activo ? 'person-dash' : 'person-check'}"></i></button>
             <button class="btn-icon text-danger" title="Eliminar usuario" onclick="eliminarUsuarioDetalle(${u.id_usuario}, '${(u.nombre + ' ' + (u.apellido || '')).replace(/'/g, "\\'")}')"><i class="bi bi-trash"></i></button>`}
      </td>
    </tr>
  `).join('');
}

function filtrarDetalleUsuarios() {
  const q = document.getElementById('buscarDetalleInput').value.trim().toLowerCase();
  if (!q) { _renderDetalleUsuarios(_todosLosUsuariosDetalle); return; }
  const filtrado = _todosLosUsuariosDetalle.filter(u =>
    `${u.nombre} ${u.apellido || ''}`.toLowerCase().includes(q) || u.correo.toLowerCase().includes(q)
  );
  _renderDetalleUsuarios(filtrado);
}

async function abrirModalDetalle(id) {
  if (!modalDetalle) modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleUsuario'));

  document.getElementById('detalleIdUsuario').value = id;
  document.getElementById('msgDetalleUsuario').style.display = 'none';
  document.getElementById('detalleNuevaPassword').value = '';
  ['detalleNombre','detalleApellido','detalleCorreo','detalleTelefono','detalleCedula','detallePregunta','detalleRespuesta'].forEach(id2 => {
    document.getElementById(id2).value = 'Cargando…';
  });
  document.getElementById('detalleNombreTitulo').textContent = '';
  modalDetalle.show();

  try {
    const r = await fetch(`${API}/superadmin/usuarios/${id}/credenciales`, { headers: headers() });
    const data = await r.json();
    const u = data.data;

    document.getElementById('detalleNombreTitulo').textContent = `${u.nombre} ${u.apellido || ''}`;
    document.getElementById('detalleNombre').value    = u.nombre || '';
    document.getElementById('detalleApellido').value  = u.apellido || '';
    document.getElementById('detalleCorreo').value    = u.correo || '';
    document.getElementById('detalleTelefono').value  = u.telefono || '';
    document.getElementById('detalleCedula').value    = u.cedula || '';
    document.getElementById('detallePregunta').value  = u.pregunta_secreta || '';
    document.getElementById('detalleRespuesta').value = u.respuesta_secreta || '';
    document.getElementById('detalleInfoExtra').value = `${u.rol} · registrado ${new Date(u.created_at).toLocaleDateString('es-EC')}`;
  } catch (e) {
    document.getElementById('msgDetalleUsuario').className = 'alert alert-danger py-2 small mt-3';
    document.getElementById('msgDetalleUsuario').textContent = 'Error al cargar los datos del usuario.';
    document.getElementById('msgDetalleUsuario').style.display = 'block';
  }
}

async function guardarDetalle() {
  const id = document.getElementById('detalleIdUsuario').value;
  const msgEl = document.getElementById('msgDetalleUsuario');
  const btn = document.getElementById('btnGuardarDetalleUsuario');
  btn.disabled = true;

  const body = {
    nombre:            document.getElementById('detalleNombre').value.trim(),
    apellido:          document.getElementById('detalleApellido').value.trim(),
    correo:            document.getElementById('detalleCorreo').value.trim(),
    telefono:          document.getElementById('detalleTelefono').value.trim(),
    cedula:            document.getElementById('detalleCedula').value.trim(),
    pregunta_secreta:  document.getElementById('detallePregunta').value.trim(),
    respuesta_secreta: document.getElementById('detalleRespuesta').value.trim(),
  };

  try {
    const r = await fetch(`${API}/superadmin/usuarios/${id}/datos-completos`, {
      method: 'PUT', headers: headers(), body: JSON.stringify(body),
    });
    const data = await r.json();
    msgEl.className = `alert py-2 small mt-3 ${data.ok ? 'alert-success' : 'alert-danger'}`;
    msgEl.textContent = data.mensaje || (data.ok ? 'Guardado.' : 'Error al guardar.');
    msgEl.style.display = 'block';
    if (data.ok) {
      _detalleUsuariosCargados = false;
      cargarDetalleUsuarios();
    }
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

async function resetPasswordDetalle() {
  const id = document.getElementById('detalleIdUsuario').value;
  const password = document.getElementById('detalleNuevaPassword').value;
  const msgEl = document.getElementById('msgDetalleUsuario');

  if (!password || password.length < 8) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'La nueva contraseña debe tener mínimo 8 caracteres.';
    msgEl.style.display = 'block';
    return;
  }

  try {
    const r = await fetch(`${API}/superadmin/usuarios/${id}/password`, {
      method: 'PUT', headers: headers(), body: JSON.stringify({ password }),
    });
    const data = await r.json();
    msgEl.className = `alert py-2 small mt-3 ${data.ok ? 'alert-success' : 'alert-danger'}`;
    msgEl.textContent = data.mensaje;
    msgEl.style.display = 'block';
    if (data.ok) document.getElementById('detalleNuevaPassword').value = '';
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  }
}

/* ══════════════════════════════════════════════════════════
   CREAR USUARIO — solo SuperAdmin
══════════════════════════════════════════════════════════ */
async function crearUsuario() {
  const msgEl = document.getElementById('msgCrearUsuario');
  const btn   = document.getElementById('btnGuardarCrearUsuario');

  const body = {
    nombre:   document.getElementById('crearNombre').value.trim(),
    apellido: document.getElementById('crearApellido').value.trim(),
    correo:   document.getElementById('crearCorreo').value.trim(),
    password: document.getElementById('crearPassword').value,
    rol:      document.getElementById('crearRol').value,
    telefono: document.getElementById('crearTelefono').value.trim(),
    cedula:   document.getElementById('crearCedula').value.trim(),
  };

  if (!body.nombre || !body.correo || !body.password) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'Nombre, correo y contraseña son obligatorios.';
    msgEl.style.display = 'block';
    return;
  }
  if (body.password.length < 8) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'La contraseña debe tener mínimo 8 caracteres.';
    msgEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  try {
    const r = await fetch(`${API}/superadmin/usuarios`, {
      method: 'POST', headers: headers(), body: JSON.stringify(body),
    });
    const data = await r.json();
    msgEl.className = `alert py-2 small mt-3 ${data.ok ? 'alert-success' : 'alert-danger'}`;
    msgEl.textContent = data.mensaje || (data.ok ? 'Usuario creado.' : 'Error al crear el usuario.');
    msgEl.style.display = 'block';

    if (data.ok) {
      _detalleUsuariosCargados = false;
      cargarDetalleUsuarios();
      setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('modalCrearUsuario'))?.hide(), 1200);
    }
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-3';
    msgEl.textContent = 'Error de conexión.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

/* ══════════════════════════════════════════════════════════
   ACTIVAR / DESACTIVAR — solo SuperAdmin
══════════════════════════════════════════════════════════ */
async function toggleActivoDetalle(id, activoActual) {
  const accion = activoActual ? 'desactivar' : 'activar';
  const ok = await confirmarAccion(`¿Seguro que quieres ${accion} esta cuenta?`, {
    titulo: activoActual ? 'Desactivar usuario' : 'Activar usuario',
    textoBoton: `Sí, ${accion}`,
    tipo: activoActual ? 'warning' : 'info',
  });
  if (!ok) return;

  try {
    const r = await fetch(`${API}/admin/usuarios/${id}/activo`, { method: 'PUT', headers: headers() });
    const data = await r.json();
    if (data.ok) {
      _detalleUsuariosCargados = false;
      cargarDetalleUsuarios();
      showToast(data.mensaje || `Usuario ${accion === 'desactivar' ? 'desactivado' : 'activado'}.`, 'success');
    } else {
      showToast(data.mensaje || `No se pudo ${accion} el usuario.`, 'error');
    }
  } catch (e) {
    showToast('Error de conexión.', 'error');
  }
}

/* ══════════════════════════════════════════════════════════
   REPORTES POR USUARIO — solo SuperAdmin
══════════════════════════════════════════════════════════ */
async function cargarReportesUsuario() {
  const tbody = document.getElementById('tbodyReportesUsuario');
  try {
    const r = await fetch(`${API}/superadmin/usuarios?por_pagina=200`, { headers: headers() });
    const data = await r.json();
    _todosLosUsuariosReporte = data.data?.data ?? [];
    _reportesUsuarioCargados = true;
    _renderReportesUsuario(_todosLosUsuariosReporte);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-danger">Error al cargar usuarios.</td></tr>';
  }
}

function _renderReportesUsuario(lista) {
  const tbody = document.getElementById('tbodyReportesUsuario');
  if (lista.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5" style="color:var(--text-muted)">Sin resultados.</td></tr>';
    return;
  }
  const rolBadgeMap = {
    superadmin: '<span class="badge" style="background:#f3e8fd;color:#9333ea;">Superadmin</span>',
    admin:      '<span class="badge" style="background:#f3e8fd;color:#a78bfa;">Admin</span>',
    usuario:    '<span class="badge" style="background:#eef4f8;color:#64748b;">Usuario</span>',
  };
  tbody.innerHTML = lista.map((u, idx) => `
    <tr>
      <td class="small text-secondary">#${idx + 1}</td>
      <td>${u.nombre} ${u.apellido || ''}</td>
      <td class="small">${u.correo}</td>
      <td>${rolBadgeMap[u.rol] || u.rol}</td>
      <td>
        <button class="btn-icon" title="Descargar reporte PDF" onclick="descargarReporteUsuario(${u.id_usuario}, '${(u.nombre + ' ' + (u.apellido || '')).replace(/'/g, "\\'")}')">
          <i class="bi bi-file-earmark-pdf"></i> Descargar reporte
        </button>
      </td>
    </tr>
  `).join('');
}

function filtrarReportesUsuario() {
  const q = document.getElementById('buscarReporteUsuarioInput').value.trim().toLowerCase();
  if (!q) { _renderReportesUsuario(_todosLosUsuariosReporte); return; }
  const filtrados = _todosLosUsuariosReporte.filter(u =>
    `${u.nombre} ${u.apellido || ''}`.toLowerCase().includes(q) || u.correo.toLowerCase().includes(q)
  );
  _renderReportesUsuario(filtrados);
}

async function descargarReporteUsuario(id, nombre) {
  try {
    const r = await fetch(`${API}/admin/usuarios/${id}/reporte-pdf`, { headers: headers() });
    if (!r.ok) throw new Error();
    const blob = await r.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `reporte-${nombre.trim().replace(/\s+/g, '-').toLowerCase()}.pdf`; a.click();
  } catch (e) {
    showToast('No se pudo generar el reporte de este usuario.', 'error');
  }
}
function eliminarUsuarioDetalle(id, nombre) {
  usuarioAEliminar = { id, nombre };
  document.getElementById('eliminarUsuarioNombre').textContent = nombre;
  document.getElementById('msgEliminarUsuario').style.display = 'none';
  document.getElementById('opcionForzarEliminar').style.display = 'none';
  document.getElementById('checkForzarEliminar').checked = false;
  document.getElementById('btnConfirmarEliminarUsuario').disabled = false;
  modalEliminar.show();
}

async function confirmarEliminarUsuario() {
  if (!usuarioAEliminar) return;
  const msgEl    = document.getElementById('msgEliminarUsuario');
  const btn      = document.getElementById('btnConfirmarEliminarUsuario');
  const forzar   = document.getElementById('checkForzarEliminar').checked;
  btn.disabled = true;

  try {
    const r = await fetch(`${API}/superadmin/usuarios/${usuarioAEliminar.id}${forzar ? '?forzar=1' : ''}`, {
      method: 'DELETE', headers: headers(),
    });
    const data = await r.json();

    if (data.ok) {
      _detalleUsuariosCargados = false;
      cargarDetalleUsuarios();
      modalEliminar.hide();
    } else {
      msgEl.className = 'alert alert-danger py-2 small mt-3 text-start';
      msgEl.textContent = data.mensaje || 'No se pudo eliminar el usuario.';
      msgEl.style.display = 'block';
      // Si falló por datos asociados y todavía no se ofreció "forzar", mostrar la opción
      if (r.status === 409 && !forzar) {
        document.getElementById('opcionForzarEliminar').style.display = 'block';
      }
      btn.disabled = false;
    }
  } catch (e) {
    msgEl.className = 'alert alert-danger py-2 small mt-3 text-start';
    msgEl.textContent = 'Error de conexión al eliminar el usuario.';
    msgEl.style.display = 'block';
    btn.disabled = false;
  }
}

/* ══════════════════════════════════════════════════════════
   DENUNCIAS DE USUARIOS — solo SuperAdmin
══════════════════════════════════════════════════════════ */
let _denunciasCargadas = [];
let _usuarioDenunciaActual = null;

const MOTIVO_DENUNCIA_LABEL = {
  acoso: 'Acoso u hostigamiento',
  spam: 'Spam o publicidad no deseada',
  contenido_inapropiado: 'Contenido inapropiado',
  comportamiento_sospechoso: 'Comportamiento sospechoso',
  otro: 'Otro motivo',
};

async function cargarDenuncias(estado = 'pendiente') {
  const tbody = document.getElementById('tbodyDenuncias');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-muted)"><i class="bi bi-arrow-repeat me-2"></i>Cargando…</td></tr>';
  try {
    const r = await fetch(`${API}/superadmin/reportes-usuario?estado=${estado}`, { headers: headers() });
    _denunciasCargadas = await r.json();
    _renderDenuncias(_denunciasCargadas);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">Error al cargar denuncias.</td></tr>';
  }
}

function _renderDenuncias(lista) {
  const tbody = document.getElementById('tbodyDenuncias');
  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-muted)">No hay denuncias en esta categoría. 🎉</td></tr>';
    return;
  }
  const rolLabel = { superadmin: 'Superadmin', admin: 'Admin', usuario: 'Usuario' };
  tbody.innerHTML = lista.map(d => `
    <tr>
      <td class="fw-semibold">${d.nombre}</td>
      <td class="small">${d.correo}</td>
      <td>${rolLabel[d.rol] || d.rol}</td>
      <td>${d.activo
        ? '<span class="badge" style="background:#dcfce7;color:#16a34a;">Activo</span>'
        : '<span class="badge" style="background:#fee2e2;color:#dc2626;">Desactivado</span>'}</td>
      <td><span class="badge bg-danger">${d.total_reportes}</span></td>
      <td class="small text-secondary">${d.ultimo_reporte_at ? new Date(d.ultimo_reporte_at).toLocaleDateString('es-EC') : '—'}</td>
      <td>
        <button class="btn-icon" onclick="verDetalleDenuncias(${d.id_usuario}, '${d.nombre.replace(/'/g, "\\'")}')">
          <i class="bi bi-eye"></i> Ver denuncias
        </button>
      </td>
    </tr>
  `).join('');
}

function filtrarDenuncias() {
  const q = document.getElementById('buscarDenunciaInput').value.trim().toLowerCase();
  if (!q) { _renderDenuncias(_denunciasCargadas); return; }
  _renderDenuncias(_denunciasCargadas.filter(d => d.nombre.toLowerCase().includes(q) || d.correo.toLowerCase().includes(q)));
}

async function _actualizarBadgeDenunciasPendientes() {
  const badge = document.getElementById('badgeDenunciasPendientes');
  try {
    const r = await fetch(`${API}/superadmin/reportes-usuario?estado=pendiente`, { headers: headers() });
    const datos = await r.json();
    const total = datos.reduce((s, d) => s + d.total_reportes, 0);
    badge.textContent = total > 99 ? '99+' : total;
    badge.style.display = total > 0 ? 'inline-block' : 'none';
  } catch (e) { /* silencioso */ }
}

async function verDetalleDenuncias(idUsuario, nombre) {
  _usuarioDenunciaActual = { id: idUsuario, nombre };
  document.getElementById('denunciaUsuarioNombre').textContent = nombre;
  const cont = document.getElementById('listaDenunciasDetalle');
  cont.innerHTML = '<div class="text-center text-secondary py-4"><i class="bi bi-arrow-repeat me-2"></i>Cargando…</div>';

  const modal = new bootstrap.Modal(document.getElementById('modalDetalleDenuncias'));
  modal.show();

  try {
    const r = await fetch(`${API}/superadmin/reportes-usuario/usuario/${idUsuario}`, { headers: headers() });
    const denuncias = await r.json();
    _renderDetalleDenuncias(denuncias);
  } catch (e) {
    cont.innerHTML = '<div class="text-center text-danger py-4">Error al cargar las denuncias.</div>';
  }

  document.getElementById('btnDesactivarDesdeDenuncia').onclick = () => {
    bootstrap.Modal.getInstance(document.getElementById('modalDetalleDenuncias'))?.hide();
    toggleActivoDetalle(idUsuario, true);
  };
  document.getElementById('btnEliminarDesdeDenuncia').onclick = () => {
    bootstrap.Modal.getInstance(document.getElementById('modalDetalleDenuncias'))?.hide();
    eliminarUsuarioDetalle(idUsuario, nombre);
  };
}

function _renderDetalleDenuncias(denuncias) {
  const cont = document.getElementById('listaDenunciasDetalle');
  const estadoBadge = {
    pendiente: '<span class="badge" style="background:#fef3c7;color:#d97706;">Pendiente</span>',
    revisado: '<span class="badge" style="background:#dcfce7;color:#16a34a;">Revisado</span>',
    descartado: '<span class="badge" style="background:#eef4f8;color:#64748b;">Descartado</span>',
  };
  cont.innerHTML = denuncias.map(d => `
    <div class="rounded-3 border p-3 mb-2" style="border-color:rgba(139,148,158,.25) !important;">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <span class="fw-semibold" style="color:#0b2340;">${MOTIVO_DENUNCIA_LABEL[d.motivo] || d.motivo}</span>
        ${estadoBadge[d.estado] || ''}
      </div>
      ${d.descripcion ? `<p class="small text-secondary mb-2">${d.descripcion.replace(/</g, '&lt;')}</p>` : ''}
      <div class="small text-secondary d-flex justify-content-between flex-wrap gap-2">
        <span>Denunciado por <strong>${d.reportante}</strong> (${d.reportante_correo || '—'})</span>
        <span>${new Date(d.created_at).toLocaleString('es-EC')}</span>
      </div>
      ${d.estado === 'pendiente' ? `
        <div class="mt-2 d-flex gap-2">
          <button class="btn btn-sm btn-outline-success" onclick="cambiarEstadoDenuncia(${d.id_reporte}, 'revisado')">Marcar revisado</button>
          <button class="btn btn-sm btn-outline-secondary" onclick="cambiarEstadoDenuncia(${d.id_reporte}, 'descartado')">Descartar</button>
        </div>` : ''}
    </div>
  `).join('') || '<div class="text-center text-secondary py-4">Sin denuncias registradas.</div>';
}

async function cambiarEstadoDenuncia(idReporte, estado) {
  try {
    const r = await fetch(`${API}/superadmin/reportes-usuario/${idReporte}/estado`, {
      method: 'PUT', headers: headers(), body: JSON.stringify({ estado }),
    });
    const data = await r.json();
    if (data.ok && _usuarioDenunciaActual) {
      showToast('Denuncia actualizada.', 'success');
      verDetalleDenuncias(_usuarioDenunciaActual.id, _usuarioDenunciaActual.nombre);
      _actualizarBadgeDenunciasPendientes();
    } else {
      showToast(data.mensaje || 'No se pudo actualizar.', 'error');
    }
  } catch (e) {
    showToast('Error de conexión.', 'error');
  }
}
