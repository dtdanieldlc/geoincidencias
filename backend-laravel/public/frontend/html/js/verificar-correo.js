/* ═══════════════════════════════════════════════════════════
   verificar-correo.js — Verificación de correo electrónico
   GeoIncidencias
═══════════════════════════════════════════════════════════ */

const API = 'https://geoincidencias-production.up.railway.app/api';

// ── Correo desde URL o sessionStorage ──────────────────────
const params = new URLSearchParams(location.search);
const correo = params.get('correo') ?? sessionStorage.getItem('correo_pendiente') ?? '';

document.getElementById('correoDestino').textContent = correo || '(correo no encontrado)';

if (!correo) {
  showMsg('No se encontró el correo. Vuelve al registro.', 'warning');
}

// ── Inputs de código ───────────────────────────────────────
const digits = document.querySelectorAll('.code-digit');

digits.forEach((inp, i) => {
  inp.addEventListener('input', e => {
    const v = e.target.value.replace(/\D/g, '');
    inp.value = v.slice(-1);
    inp.classList.toggle('filled', !!inp.value);
    if (inp.value && i < 5) digits[i + 1].focus();
    if (getCode().length === 6) verificar();
  });

  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && i > 0) {
      digits[i - 1].value = '';
      digits[i - 1].classList.remove('filled');
      digits[i - 1].focus();
    }
  });

  inp.addEventListener('paste', e => {
    e.preventDefault();
    const txt = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    txt.split('').forEach((c, j) => {
      if (digits[j]) { digits[j].value = c; digits[j].classList.add('filled'); }
    });
    if (txt.length === 6) { digits[5].focus(); verificar(); }
  });
});

digits[0].focus();

function getCode() {
  return [...digits].map(d => d.value).join('');
}

function resetInputs() {
  digits.forEach(d => {
    d.classList.add('input-error');
    d.classList.remove('filled');
  });
  setTimeout(() => digits.forEach(d => {
    d.classList.remove('input-error');
    d.value = '';
  }), 600);
  digits[0].focus();
}

// ── Verificar código ───────────────────────────────────────
document.getElementById('btnVerificar').addEventListener('click', verificar);

async function verificar() {
  const codigo = getCode();
  if (codigo.length < 6) { showMsg('Ingresa los 6 dígitos del código.', 'error'); return; }

  setBtnLoading(true);
  hideMsg();

  try {
    const r = await fetch(`${API}/auth/verificar-correo`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ correo, codigo }),
    });
    const d = await r.json();

    if (d.ok) {
      showMsg('¡Correo verificado! Redirigiendo al inicio de sesión…', 'success');
      sessionStorage.removeItem('correo_pendiente');
      setTimeout(() => { location.href = 'login.html'; }, 2000);
    } else {
      if (d.expirado) {
        showMsg('El código ha expirado. Solicita uno nuevo.', 'warning');
      } else {
        showMsg(d.mensaje ?? 'Código incorrecto.', 'error');
        resetInputs();
      }
    }
  } catch {
    showMsg('Error de conexión. Intenta de nuevo.', 'error');
  } finally {
    setBtnLoading(false);
  }
}

// ── Reenviar código ────────────────────────────────────────
let reenviarBloqueado = false;

document.getElementById('btnReenviar').addEventListener('click', reenviarCodigo);

async function reenviarCodigo() {
  if (reenviarBloqueado || !correo) return;

  reenviarBloqueado = true;
  document.getElementById('btnReenviar').disabled = true;
  document.getElementById('reenviarTimer').style.display = 'inline';
  iniciarTimer(60);
  hideMsg();

  try {
    const r = await fetch(`${API}/auth/reenviar-codigo`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ correo }),
    });
    const d = await r.json();
    showMsg(d.mensaje ?? (d.ok ? 'Código reenviado.' : 'Error al reenviar.'), d.ok ? 'success' : 'error');
  } catch {
    showMsg('Error de conexión.', 'error');
  }
}

function iniciarTimer(seg) {
  const span = document.getElementById('timerSeg');
  const btn  = document.getElementById('btnReenviar');
  span.textContent = seg;

  const iv = setInterval(() => {
    seg--;
    span.textContent = seg;
    if (seg <= 0) {
      clearInterval(iv);
      reenviarBloqueado = false;
      btn.disabled = false;
      document.getElementById('reenviarTimer').style.display = 'none';
    }
  }, 1000);
}

// ── Helpers UI ─────────────────────────────────────────────
function showMsg(msg, type) {
  const box = document.getElementById('msgBox');
  box.textContent = msg;
  box.className   = `msg ${type}`;
  box.style.display = 'block';
}

function hideMsg() {
  document.getElementById('msgBox').style.display = 'none';
}

function setBtnLoading(loading) {
  document.getElementById('btnVerificar').disabled        = loading;
  document.getElementById('btnVerificarTxt').textContent  = loading ? 'Verificando…' : 'Verificar código';
}
