// frontend/js/login.js
const API = 'https://geoincidencias-production.up.railway.app/api';

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
  document.getElementById('formRegistro').style.display  = tab==='registro' ? '' : 'none';
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
  const nombre   = document.getElementById('regNombre').value.trim();
  const apellido = document.getElementById('regApellido').value.trim();
  const correo   = document.getElementById('regCorreo').value.trim();
  const telefono = document.getElementById('regTelefono').value.trim();
  const password = document.getElementById('regPassword').value;

  if (!nombre || !correo || !password) return mostrarAlerta('Completa los campos obligatorios.', 'warning');
  if (password.length < 6) return mostrarAlerta('La contraseña debe tener al menos 6 caracteres.', 'warning');

  const btn = document.getElementById('btnRegistro');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Creando…';

  try {
    const res  = await fetch(`${API}/auth/registro`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ nombre, apellido, correo, telefono, password })
    });
    const data = await res.json();
    if (res.ok && data.ok) {
      mostrarAlerta('Cuenta creada. Ya puedes iniciar sesión.', 'success');
      mostrarTab('login');
      document.getElementById('loginCorreo').value = correo;
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo crear la cuenta.', 'danger');
    }
  } catch(e) {
    mostrarAlerta('No se pudo conectar con el servidor.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-person-plus me-2"></i>Crear cuenta';
  }
}

// Si ya hay sesión activa, saltar directo al dashboard
if (localStorage.getItem('gi_token')) {
  window.location.href = 'index.html';
}
