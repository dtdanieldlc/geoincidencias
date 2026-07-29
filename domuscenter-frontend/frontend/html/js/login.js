// frontend/js/login.js
const API = 'https://geoincidencias-production.up.railway.app/api';

// ────────────────────────────────────────────────────────────────
//  Inicio de sesión con Google (Google Identity Services)
// ────────────────────────────────────────────────────────────────
const GOOGLE_CLIENT_ID = '342117746744-j5gber9k4e4db7ciutf7ghd167re4uoi.apps.googleusercontent.com';

window.addEventListener('load', () => {
  if (!window.google || !document.getElementById('googleBtnLogin')) return;

  google.accounts.id.initialize({
    client_id: GOOGLE_CLIENT_ID,
    callback: manejarRespuestaGoogle,
  });

  google.accounts.id.renderButton(
    document.getElementById('googleBtnLogin'),
    { theme: 'outline', size: 'large', width: 320, text: 'continue_with', locale: 'es' }
  );
});

async function manejarRespuestaGoogle(respuesta) {
  try {
    const res  = await fetch(`${API}/auth/google`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ credential: respuesta.credential })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      localStorage.setItem('gi_token', data.token);
      localStorage.setItem('gi_usuario', JSON.stringify(data.usuario));
      window.location.href = 'index.html';
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo iniciar sesión con Google.', 'danger');
    }
  } catch (e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  }
}

function mostrarAlerta(msg, tipo='danger') {
  const el = document.getElementById('alerta');
  el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show shadow-lg border-0" role="alert">
    <i class="bi bi-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 4500);
}

function mostrarTab(tab) {
  document.getElementById('formLogin').style.display    = tab==='login' ? '' : 'none';
  document.getElementById('formRegistro').style.display  = 'none'; // registro público deshabilitado
  document.getElementById('tabLoginBtn').classList.toggle('active', tab==='login');
  document.getElementById('tabRegistroBtn').classList.toggle('active', tab==='registro');
}

async function iniciarSesion() {
  const correo   = document.getElementById('loginCorreo').value.trim();
  const password = document.getElementById('loginPassword').value;
  if (!correo || !password) return mostrarAlerta('Completa correo y contraseña.', 'warning');

  const btn = document.getElementById('btnLogin');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Verificando…';

  try {
    const res  = await fetch(`${API}/auth/login`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ correo, password })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      localStorage.setItem('gi_token', data.token);
      localStorage.setItem('gi_usuario', JSON.stringify(data.usuario));
      window.location.href = 'index.html';
    } else {
      mostrarAlerta(data.mensaje || 'Credenciales incorrectas.', 'danger');
    }
  } catch(e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión';
  }
}

async function crearCuenta() {
  mostrarAlerta('El registro público está deshabilitado. Solicita al administrador que te den de alta.', 'warning');
}

// ────────────────────────────────────────────────────────────────
//  Recuperar contraseña (cédula + pregunta secreta)
// ────────────────────────────────────────────────────────────────
let recTokenTemporal = null;

function abrirRecuperar(e) {
  e.preventDefault();
  document.getElementById('recuperarPaso1').style.display = '';
  document.getElementById('recuperarPaso2').style.display = 'none';
  document.getElementById('recuperarPaso3').style.display = 'none';
  document.getElementById('recCorreo').value = '';
  document.getElementById('recCedula').value = '';
  document.getElementById('recRespuesta').value = '';
  document.getElementById('recPasswordNueva').value = '';
  document.getElementById('recPasswordConfirmar').value = '';
  recTokenTemporal = null;
  new bootstrap.Modal(document.getElementById('modalRecuperar')).show();
}

async function recuperarPedirPregunta() {
  const correo = document.getElementById('recCorreo').value.trim();
  const cedula = document.getElementById('recCedula').value.trim();
  if (!correo || !cedula) return mostrarAlerta('Ingresa correo y cédula.', 'warning');

  const btn = document.getElementById('btnRecPaso1');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Verificando…';

  try {
    const res  = await fetch(`${API}/auth/recuperar/pregunta`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ correo, cedula })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      document.getElementById('recPreguntaTexto').textContent = data.pregunta_secreta;
      document.getElementById('recuperarPaso1').style.display = 'none';
      document.getElementById('recuperarPaso2').style.display = '';
    } else {
      mostrarAlerta(data.mensaje || 'No encontramos una cuenta con esos datos.', 'danger');
    }
  } catch(e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Continuar';
  }
}

async function recuperarVerificarRespuesta() {
  const correo    = document.getElementById('recCorreo').value.trim();
  const cedula    = document.getElementById('recCedula').value.trim();
  const respuesta = document.getElementById('recRespuesta').value.trim();
  if (!respuesta) return mostrarAlerta('Escribe tu respuesta.', 'warning');

  const btn = document.getElementById('btnRecPaso2');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Verificando…';

  try {
    const res  = await fetch(`${API}/auth/recuperar/verificar`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ correo, cedula, respuesta_secreta: respuesta })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      recTokenTemporal = data.token;
      document.getElementById('recuperarPaso2').style.display = 'none';
      document.getElementById('recuperarPaso3').style.display = '';
    } else {
      mostrarAlerta(data.mensaje || 'La respuesta no es correcta.', 'danger');
    }
  } catch(e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Verificar';
  }
}

async function recuperarGuardarPassword() {
  const nueva     = document.getElementById('recPasswordNueva').value;
  const confirmar = document.getElementById('recPasswordConfirmar').value;

  if (!nueva || nueva.length < 6) return mostrarAlerta('La contraseña debe tener al menos 6 caracteres.', 'warning');
  if (nueva !== confirmar) return mostrarAlerta('Las contraseñas no coinciden.', 'warning');
  if (!recTokenTemporal) return mostrarAlerta('La verificación expiró, vuelve a intentarlo.', 'danger');

  const btn = document.getElementById('btnRecPaso3');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Guardando…';

  try {
    const res  = await fetch(`${API}/auth/recuperar/reset`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ token: recTokenTemporal, password_nuevo: nueva })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      mostrarAlerta('Contraseña actualizada. Ya puedes iniciar sesión.', 'success');
      bootstrap.Modal.getInstance(document.getElementById('modalRecuperar')).hide();
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo actualizar la contraseña.', 'danger');
    }
  } catch(e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Guardar nueva contraseña';
  }
}

// Si ya hay sesión activa, saltar directo al dashboard
if (localStorage.getItem('gi_token')) {
  window.location.href = 'index.html';
}
// Cargar sucursales para el registro (endpoint público)
async function cargarSucursalesRegistro() {
  const sel = document.getElementById('regSucursal');
  if (!sel) return;
  try {
    const r = await fetch(`${API}/catalogos/sucursales-publicas`);
    const datos = await r.json();
    const lista = Array.isArray(datos) ? datos : [];
    sel.innerHTML = '<option value="">¿En qué sucursal trabajas? *</option>' +
      lista.map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
  } catch (e) {
    sel.innerHTML = '<option value="">No se pudieron cargar sucursales</option>';
  }
}
cargarSucursalesRegistro();
