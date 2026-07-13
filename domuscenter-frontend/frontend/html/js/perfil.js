// frontend/html/js/perfil.js
exigirSesion();

let usuarioActual = null;

// Convierte una ruta relativa (/storage/...) en una URL completa hacia el backend
function urlCompleta(ruta) {
  if (!ruta) return null;
  if (ruta.startsWith('http')) return ruta;
  const base = (typeof API !== 'undefined' ? API : '').replace('/api', '');
  return base + ruta;
}

async function cargarPerfil() {
  try {
    const token = getToken();
    if (!token) {
      mostrarAlerta('No hay sesión activa', 'danger');
      return;
    }

    const r = await fetch(`${API}/auth/perfil`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      }
    });

    if (!r.ok) {
      const errorText = await r.text();
      console.error('Error al cargar perfil:', r.status, errorText);
      mostrarAlerta(`Error al cargar perfil (${r.status})`, 'danger');
      return;
    }

    usuarioActual = await r.json();

    // Datos de encabezado
    document.getElementById('nombrePerfil').textContent =
      `${usuarioActual.nombre || ''} ${usuarioActual.apellido || ''}`.trim();
    document.getElementById('rolPerfil').textContent =
      usuarioActual.rol === 'superadmin' ? 'Superadmin' : (usuarioActual.rol === 'admin' ? 'Administrador' : 'Usuario');
    document.getElementById('saldoIncentivos').textContent =
      `$${parseFloat(usuarioActual.saldo_incentivos || 0).toFixed(2)}`;

    const fechaCreacion = usuarioActual.created_at
      ? new Date(usuarioActual.created_at).toLocaleDateString('es-EC')
      : '—';
    document.getElementById('miembroDesde').textContent = fechaCreacion;

    // Rellenar formulario
    document.getElementById('editNombre').value    = usuarioActual.nombre    || '';
    document.getElementById('editApellido').value  = usuarioActual.apellido  || '';
    document.getElementById('editCorreo').value    = usuarioActual.correo    || '';
    document.getElementById('editTelefono').value  = usuarioActual.telefono  || '';
    document.getElementById('editCedula').value = usuarioActual.cedula || '';

    // Muestra el bloque de pregunta secreta SOLO si el usuario aún no la tiene configurada
    document.getElementById('cardPreguntaSecreta').style.display =
      usuarioActual.tiene_pregunta_secreta ? 'none' : 'block';

    // Mostrar foto guardada si existe
    if (usuarioActual.foto_url) {
      const imgSrc = urlCompleta(usuarioActual.foto_url);
      const img = document.getElementById('avatarImg');
      img.src = imgSrc;
      img.style.display = 'block';
      document.getElementById('avatarIcon').style.display = 'none';
    } else {
      document.getElementById('avatarImg').style.display = 'none';
      document.getElementById('avatarIcon').style.display = 'block';
    }

  } catch (e) {
    console.error('Error en cargarPerfil:', e);
    mostrarAlerta('Error de conexión al cargar perfil: ' + e.message, 'danger');
  }
}

async function guardarPerfil() {
  const nombre   = document.getElementById('editNombre').value.trim();
  const apellido = document.getElementById('editApellido').value.trim();
  const correo   = document.getElementById('editCorreo').value.trim();
  const telefono = document.getElementById('editTelefono').value.trim();
  const cedula   = document.getElementById('editCedula').value.trim();

  if (!nombre || !correo) {
    return mostrarAlerta('Nombre y correo son obligatorios', 'warning');
  }
  if (cedula && !/^\d{10}$/.test(cedula)) {
    return mostrarAlerta('La cédula debe tener 10 dígitos.', 'warning');
  }

  const btn = document.getElementById('btnGuardarPerfil');
  btn.disabled = true;

  try {
    const token = getToken();
    const res = await fetch(`${API}/auth/perfil`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ nombre, apellido, correo, telefono, cedula }),
    });

    const data = await res.json();

    if (res.ok && data.ok) {
      mostrarAlerta('Perfil actualizado correctamente', 'success');
      if (typeof inicializarBarraUsuario === 'function') inicializarBarraUsuario();
      cargarPerfil();
    } else {
      mostrarAlerta(data.mensaje || 'Error al actualizar perfil', 'danger');
    }
  } catch (e) {
    console.error('Error en guardarPerfil:', e);
    mostrarAlerta('Error de conexión: ' + e.message, 'danger');
  } finally {
    btn.disabled = false;
  }
}

function togglePass(id) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}

async function cambiarPassword() {
  const actual   = document.getElementById('password_actual').value;
  const nuevo    = document.getElementById('password_nuevo').value;
  const confirm  = document.getElementById('password_confirm').value;

  if (!actual || !nuevo || !confirm)
    return mostrarAlerta('Todos los campos son obligatorios', 'warning');
  if (nuevo.length < 6)
    return mostrarAlerta('La nueva contraseña debe tener mínimo 6 caracteres', 'warning');
  if (nuevo !== confirm)
    return mostrarAlerta('Las contraseñas no coinciden', 'warning');

  try {
    const token = getToken();
    const res = await fetch(`${API}/auth/cambiar-password`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ password_actual: actual, password_nuevo: nuevo })
    });

    const data = await res.json();

    if (res.ok && data.ok) {
      mostrarAlerta('Contraseña actualizada correctamente', 'success');
      document.getElementById('password_actual').value = '';
      document.getElementById('password_nuevo').value  = '';
      document.getElementById('password_confirm').value = '';
    } else {
      mostrarAlerta(data.mensaje || 'Error al cambiar contraseña', 'danger');
    }
  } catch (e) {
    console.error('Error en cambiarPassword:', e);
    mostrarAlerta('Error de conexión', 'danger');
  }
}

async function guardarPreguntaSecreta() {
  const pregunta_secreta  = document.getElementById('editPregunta').value;
  const respuesta_secreta = document.getElementById('editRespuesta').value.trim();

  if (!respuesta_secreta || respuesta_secreta.length < 2) {
    return mostrarAlerta('Escribe una respuesta secreta válida', 'warning');
  }

  const btn = document.getElementById('btnGuardarPregunta');
  btn.disabled = true;

  try {
    const token = getToken();
    const res = await fetch(`${API}/auth/perfil`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        nombre: usuarioActual.nombre,
        apellido: usuarioActual.apellido,
        correo: usuarioActual.correo,
        telefono: usuarioActual.telefono,
        cedula: usuarioActual.cedula,
        pregunta_secreta,
        respuesta_secreta,
      }),
    });

    const data = await res.json();

    if (res.ok && data.ok) {
      mostrarAlerta('Pregunta de seguridad configurada correctamente', 'success');
      cargarPerfil(); // esto vuelve a ocultar el bloque porque ya quedó configurada
    } else {
      mostrarAlerta(data.mensaje || 'Error al guardar la pregunta de seguridad', 'danger');
    }
  } catch (e) {
    console.error('Error en guardarPreguntaSecreta:', e);
    mostrarAlerta('Error de conexión', 'danger');
  } finally {
    btn.disabled = false;
  }
}

// ── Foto de perfil ──────────────────────────────────────────────────────────
document.getElementById('fotoInput').addEventListener('change', async function (e) {
  const file = e.target.files[0];
  if (!file) return;

  if (!file.type.startsWith('image/')) {
    mostrarAlerta('Solo se permiten imágenes', 'warning');
    return;
  }

  if (file.size > 2 * 1024 * 1024) {
    mostrarAlerta('La imagen no debe superar 2 MB', 'warning');
    return;
  }

  // Preview local inmediato
  const reader = new FileReader();
  reader.onload = function (ev) {
    const img = document.getElementById('avatarImg');
    img.src = ev.target.result;
    img.style.display = 'block';
    document.getElementById('avatarIcon').style.display = 'none';
  };
  reader.readAsDataURL(file);

  // Subida al backend
  const formData = new FormData();
  formData.append('foto', file);

  try {
    const token = getToken();
    const res = await fetch(`${API}/auth/foto`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        // NO incluir Content-Type aquí — el navegador lo pone solo con el boundary correcto
      },
      body: formData,
    });

    const data = await res.json();

    if (res.ok && data.ok) {
      mostrarAlerta('Foto actualizada correctamente', 'success');
      // Actualizar src con la URL definitiva del servidor
      document.getElementById('avatarImg').src = urlCompleta(data.foto_url);
    } else {
      mostrarAlerta(data.mensaje || 'Error al subir la foto', 'danger');
    }
  } catch (err) {
    console.error('Error al subir foto:', err);
    mostrarAlerta('Error de conexión al subir la foto', 'danger');
  }
});

// ── Alerta flotante ─────────────────────────────────────────────────────────
function mostrarAlerta(msg, tipo = 'success') {
  const el = document.getElementById('alerta');
  el.innerHTML = `
    <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  el.style.display = 'block';
  setTimeout(() => { el.innerHTML = ''; el.style.display = 'none'; }, 4000);
}

// ── Init ────────────────────────────────────────────────────────────────────
if (typeof inicializarBarraUsuario === 'function') inicializarBarraUsuario();
cargarPerfil();

// ── Zona de peligro: desactivar / eliminar cuenta ────────────────────────────

async function confirmarDesactivarCuenta() {
  const ok = await confirmarAccion(
    'No podrás iniciar sesión hasta que un administrador la reactive.',
    { titulo: '¿Desactivar tu cuenta?', textoBoton: 'Sí, desactivar', tipo: 'warning' }
  );
  if (!ok) return;
  desactivarCuenta();
}

async function desactivarCuenta() {
  try {
    const res  = await fetch(`${API}/auth/desactivar`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
      }
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      mostrarAlerta('Tu cuenta ha sido desactivada.', 'success');
      setTimeout(cerrarSesion, 1500);
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo desactivar la cuenta.', 'danger');
    }
  } catch (e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  }
}

// Muestra el correo del usuario en el modal cuando se abre
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modalEliminarCuenta');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', () => {
    document.getElementById('correoConfirmarEliminar').textContent = usuarioActual?.correo || '';
    document.getElementById('inputConfirmarCorreo').value = '';
  });
});

async function eliminarCuenta() {
  const correoEscrito = document.getElementById('inputConfirmarCorreo').value.trim();

  if (!usuarioActual || correoEscrito.toLowerCase() !== (usuarioActual.correo || '').toLowerCase()) {
    mostrarAlerta('El correo escrito no coincide con tu cuenta.', 'warning');
    return;
  }

  const btn = document.getElementById('btnConfirmarEliminarCuenta');
  btn.disabled = true;
  btn.innerHTML = 'Eliminando…';

  try {
    const res  = await fetch(`${API}/auth/eliminar`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ confirmar_correo: correoEscrito })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      mostrarAlerta('Tu cuenta ha sido eliminada.', 'success');
      setTimeout(cerrarSesion, 1500);
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo eliminar la cuenta.', 'danger');
    }
  } catch (e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Sí, eliminar mi cuenta';
  }
}