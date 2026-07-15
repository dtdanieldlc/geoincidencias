// frontend/js/mensajes.js
exigirSesion();

// Convierte una ruta relativa (/storage/...) que devuelve el backend en una
// URL completa. Sin esto, el navegador intenta cargar la foto desde el
// dominio del FRONTEND en vez del backend, y la imagen sale rota.
function urlCompleta(ruta) {
  if (!ruta) return null;
  if (ruta.startsWith('http')) return ruta;
  const base = (typeof API !== 'undefined' ? API : '').replace('/api', '');
  return base + ruta;
}

// ────────────────────────────────────────────────────────────────
//  Configuración de Pusher (mismo par de valores en las 2 puntas)
// ────────────────────────────────────────────────────────────────
const PUSHER_KEY     = 'fef986fe88a6b220d256';
const PUSHER_CLUSTER = 'sa1';

let conversaciones      = [];
let conversacionActiva  = null; // objeto conversación completa
let usuarioYo           = null;
let pusher, canalPrivado;

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

function iniciales(nombre) {
  return (nombre || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase() ?? '').join('');
}

// Devuelve el HTML interno del círculo de avatar: la foto de perfil si
// existe, o las iniciales del nombre como respaldo.
function avatarInner(nombre, fotoUrl) {
  const src = urlCompleta(fotoUrl);
  const ini = _escaparHtml(iniciales(nombre));
  const contenido = src
    ? `<img src="${src}" alt="${_escaparHtml(nombre || '')}" style="display:block;width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='${ini}';">`
    : iniciales(nombre);
  return `<span class="avatar-media">${contenido}</span>`;
}

function formatoHora(fecha) {
  const d = new Date(fecha);
  return d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' });
}

function formatoFechaRelativa(fecha) {
  const d = new Date(fecha), hoy = new Date();
  const mismodia = d.toDateString() === hoy.toDateString();
  if (mismodia) return formatoHora(fecha);
  return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit' });
}

// ════════════════════════════════════════════════════════
//  Cargar lista de conversaciones
// ════════════════════════════════════════════════════════
async function cargarConversaciones() {
  const cont = document.getElementById('listaConversacionesBody');
  try {
    const r = await fetchAPI(`${API}/chat/conversaciones`);
    conversaciones = await r.json();
    _renderConversaciones(conversaciones);
  } catch (e) {
    cont.innerHTML = '<div class="text-center text-danger py-4 small">Error al cargar conversaciones.</div>';
  }
}

function _renderConversaciones(lista) {
  const cont = document.getElementById('listaConversacionesBody');
  if (!lista.length) {
    cont.innerHTML = '<div class="text-center text-secondary py-5 small">Aún no tienes conversaciones.<br>Toca "Nuevo mensaje" para empezar.</div>';
    return;
  }
  cont.innerHTML = lista.map(c => `
    <div class="conv-item ${conversacionActiva?.id_conversacion === c.id_conversacion ? 'active' : ''}" onclick="abrirConversacion(${c.id_conversacion})">
      <div class="conv-avatar">
        ${avatarInner(c.otro.nombre, c.otro.foto_url)}
        ${c.otro.en_linea ? '<span class="dot-online"></span>' : ''}
      </div>
      <div class="flex-grow-1" style="min-width:0;">
        <div class="d-flex justify-content-between align-items-center">
          <span class="conv-nombre">${c.otro.nombre}</span>
          <span class="conv-hora">${c.ultimo_mensaje_at ? formatoFechaRelativa(c.ultimo_mensaje_at) : ''}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="conv-preview">${c.ultimo_mensaje_mio ? 'Tú: ' : ''}${c.ultimo_mensaje || 'Inicia la conversación'}</span>
          ${c.no_leidos > 0 ? `<span class="conv-badge">${c.no_leidos}</span>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

function filtrarConversaciones() {
  const q = document.getElementById('buscarConversacionInput').value.trim().toLowerCase();
  if (!q) { _renderConversaciones(conversaciones); return; }
  _renderConversaciones(conversaciones.filter(c => c.otro.nombre.toLowerCase().includes(q)));
}

// ════════════════════════════════════════════════════════
//  Abrir una conversación y cargar sus mensajes
// ════════════════════════════════════════════════════════
// En celular: oculta el chat y vuelve a mostrar la lista de conversaciones
function volverAListaMovil(e) {
  e?.stopPropagation();
  document.getElementById('chatWrap').classList.remove('chat-abierto-movil');
}

async function abrirConversacion(idConversacion) {
  conversacionActiva = conversaciones.find(c => c.id_conversacion === idConversacion);
  if (!conversacionActiva) return;
  cerrarBusquedaMensajes();

  document.getElementById('chatVacio').style.display = 'none';
  const chatActivo = document.getElementById('chatActivo');
  chatActivo.style.display = 'flex';
  document.getElementById('chatWrap').classList.add('chat-abierto-movil');

  document.getElementById('chatAvatarOtro').innerHTML = avatarInner(conversacionActiva.otro.nombre, conversacionActiva.otro.foto_url);
  document.getElementById('chatNombreOtro').textContent = conversacionActiva.otro.nombre;
  document.getElementById('chatEstadoOtro').textContent = conversacionActiva.otro.en_linea ? 'En línea' : '';

  _renderConversaciones(conversaciones); // refresca el resaltado "active"

  const scroll = document.getElementById('mensajesScroll');
  scroll.innerHTML = '<div class="text-center text-secondary py-4 small">Cargando mensajes…</div>';

  try {
    const r = await fetchAPI(`${API}/chat/conversaciones/${idConversacion}/mensajes`);
    const mensajes = await r.json();
    _renderMensajes(mensajes);
    // Ya se marcaron como leídos en el backend; refrescamos el badge de la lista
    conversacionActiva.no_leidos = 0;
    const enLista = conversaciones.find(c => c.id_conversacion === idConversacion);
    if (enLista) enLista.no_leidos = 0;
    _renderConversaciones(conversaciones);
    if (typeof _actualizarBadgeMensajes === 'function') _actualizarBadgeMensajes();
  } catch (e) {
    scroll.innerHTML = '<div class="text-center text-danger py-4 small">Error al cargar mensajes.</div>';
  }

  document.getElementById('inputMensaje').focus();
}

function _checkIcon(m) {
  if (m.id_usuario_emisor !== usuarioYo.id_usuario) return '';
  const leido = !!m.leido_at;
  return `<i class="bi ${leido ? 'bi-check2-all' : 'bi-check2'} msg-check ${leido ? 'leido' : ''}"></i>`;
}

function _contenidoBurbuja(m) {
  if (m.tipo === 'imagen' && m.imagen_url) {
    const src = urlCompleta(m.imagen_url);
    return `<img src="${src}" class="msg-imagen" onclick="window.open('${src}','_blank')" alt="Imagen enviada">`;
  }
  return _escaparHtml(m.contenido);
}

function _renderMensajes(mensajes) {
  const scroll = document.getElementById('mensajesScroll');
  if (!mensajes.length) {
    scroll.innerHTML = '<div class="text-center text-secondary py-4 small">Aún no hay mensajes. ¡Saluda! 👋</div>';
    return;
  }
  scroll.innerHTML = mensajes.map(m => `
    <div class="msg-burbuja ${m.id_usuario_emisor === usuarioYo.id_usuario ? 'msg-mio' : 'msg-otro'}" data-id-mensaje="${m.id_mensaje}">
      ${_contenidoBurbuja(m)}
      <span class="msg-hora">${formatoHora(m.created_at)} ${_checkIcon(m)}</span>
    </div>
  `).join('');
  scroll.scrollTop = scroll.scrollHeight;
}

function _escaparHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto;
  return div.innerHTML;
}

function _agregarMensajeAlChat(m) {
  const scroll = document.getElementById('mensajesScroll');
  // Evita pintar el mismo mensaje dos veces (p. ej. si Pusher lo reenvía
  // tras una reconexión).
  if (m.id_mensaje && scroll.querySelector(`[data-id-mensaje="${m.id_mensaje}"]`)) return;
  const vacio = scroll.querySelector('.text-secondary');
  if (vacio && scroll.children.length === 1) scroll.innerHTML = '';
  const div = document.createElement('div');
  div.className = `msg-burbuja ${m.id_usuario_emisor === usuarioYo.id_usuario ? 'msg-mio' : 'msg-otro'}`;
  if (m.id_mensaje) div.dataset.idMensaje = m.id_mensaje;
  div.innerHTML = `${_contenidoBurbuja(m)}<span class="msg-hora">${formatoHora(m.created_at)} ${_checkIcon(m)}</span>`;
  scroll.appendChild(div);
  scroll.scrollTop = scroll.scrollHeight;
}

// Cuando el otro abre la conversación y lee mis mensajes, Pusher nos avisa
// y acá pintamos el doble check azul en todos los que ya envié.
function _marcarMisMensajesComoLeidos() {
  document.querySelectorAll('#mensajesScroll .msg-mio .msg-check').forEach(el => {
    el.classList.remove('bi-check2');
    el.classList.add('bi-check2-all', 'leido');
  });
}

// ════════════════════════════════════════════════════════
//  Enviar un mensaje (funciona tanto para conversaciones ya
//  existentes como para una recién iniciada desde el directorio)
// ════════════════════════════════════════════════════════
async function enviarMensaje(e) {
  e.preventDefault();
  if (!conversacionActiva) return;
  const input = document.getElementById('inputMensaje');
  const texto = input.value.trim();
  if (!texto) return;
  input.value = '';

  try {
    const r = await fetchAPI(`${API}/chat/mensajes`, {
      method: 'POST',
      body: JSON.stringify({ id_usuario_destino: conversacionActiva.otro.id_usuario, contenido: texto }),
    });
    const data = await r.json();
    if (data.ok) {
      _agregarMensajeAlChat(data.mensaje);
      if (!conversacionActiva.id_conversacion) {
        conversacionActiva.id_conversacion = data.mensaje.id_conversacion;
      }
      await cargarConversaciones();
      const actualizada = conversaciones.find(c => c.id_conversacion === conversacionActiva.id_conversacion);
      if (actualizada) conversacionActiva = actualizada;
      _renderConversaciones(conversaciones);
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo enviar el mensaje.', 'danger');
    }
  } catch (err) {
    mostrarAlerta('Error de conexión al enviar el mensaje.', 'danger');
  }
}

// ════════════════════════════════════════════════════════
//  Enviar una imagen
// ════════════════════════════════════════════════════════
async function enviarImagenChat(e) {
  const file = e.target.files[0];
  e.target.value = '';
  if (!file || !conversacionActiva) return;

  if (file.size > 5 * 1024 * 1024) {
    mostrarAlerta('La imagen no puede pesar más de 5MB.', 'danger');
    return;
  }

  const formData = new FormData();
  formData.append('imagen', file);
  formData.append('id_usuario_destino', conversacionActiva.otro.id_usuario);

  try {
    const res = await fetch(`${API}/chat/mensajes/imagen`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        // NO Content-Type: el navegador pone el boundary correcto solo
      },
      body: formData,
    });
    const data = await res.json();
    if (data.ok) {
      _agregarMensajeAlChat(data.mensaje);
      if (!conversacionActiva.id_conversacion) conversacionActiva.id_conversacion = data.mensaje.id_conversacion;
      await cargarConversaciones();
      const actualizada = conversaciones.find(c => c.id_conversacion === conversacionActiva.id_conversacion);
      if (actualizada) conversacionActiva = actualizada;
      _renderConversaciones(conversaciones);
    } else {
      mostrarAlerta(data.mensaje || 'No se pudo enviar la imagen.', 'danger');
    }
  } catch (err) {
    mostrarAlerta('Error de conexión al enviar la imagen.', 'danger');
  }
}

// ════════════════════════════════════════════════════════
//  Indicador "escribiendo…"
// ════════════════════════════════════════════════════════
let _ultimoAvisoEscribiendo = 0;
let _timeoutOcultarEscribiendo = null;

function notificarEscribiendo() {
  if (!conversacionActiva || !conversacionActiva.otro?.id_usuario) return;
  const ahora = Date.now();
  if (ahora - _ultimoAvisoEscribiendo < 2500) return; // throttle: cada 2.5s máximo
  _ultimoAvisoEscribiendo = ahora;

  fetchAPI(`${API}/chat/escribiendo`, {
    method: 'POST',
    body: JSON.stringify({ id_usuario_destino: conversacionActiva.otro.id_usuario }),
  }).catch(() => {});
}

function _mostrarIndicadorEscribiendo(nombreEmisor) {
  const el = document.getElementById('indicadorEscribiendo');
  el.innerHTML = `<i class="bi bi-three-dots"></i> ${_escaparHtml(nombreEmisor)} está escribiendo…`;
  el.style.display = 'block';
  clearTimeout(_timeoutOcultarEscribiendo);
  _timeoutOcultarEscribiendo = setTimeout(() => { el.style.display = 'none'; }, 3000);
}

// ════════════════════════════════════════════════════════
//  Buscar dentro de la conversación abierta
// ════════════════════════════════════════════════════════
let _resultadosBusqueda = [];
let _indiceBusquedaActual = -1;

function toggleBusquedaMensajes(e) {
  e?.stopPropagation();
  const barra = document.getElementById('barraBusquedaMensajes');
  const abierta = barra.style.display === 'flex';
  if (abierta) { cerrarBusquedaMensajes(); return; }
  barra.style.display = 'flex';
  document.getElementById('inputBuscarMensajes').value = '';
  document.getElementById('inputBuscarMensajes').focus();
}

function cerrarBusquedaMensajes() {
  document.getElementById('barraBusquedaMensajes').style.display = 'none';
  document.getElementById('contadorBusqueda').textContent = '';
  _resultadosBusqueda = [];
  _indiceBusquedaActual = -1;
  document.querySelectorAll('#mensajesScroll .msg-burbuja').forEach(b => {
    b.classList.remove('msg-atenuado', 'msg-resaltado-actual');
    const marks = b.querySelectorAll('mark');
    marks.forEach(m => { m.replaceWith(document.createTextNode(m.textContent)); });
  });
}

function _ejecutarBusquedaMensajes() {
  const q = document.getElementById('inputBuscarMensajes').value.trim().toLowerCase();
  const burbujas = Array.from(document.querySelectorAll('#mensajesScroll .msg-burbuja'));
  const contador = document.getElementById('contadorBusqueda');

  // Reset visual
  burbujas.forEach(b => {
    b.classList.remove('msg-atenuado', 'msg-resaltado-actual');
    b.querySelectorAll('mark').forEach(m => m.replaceWith(document.createTextNode(m.textContent)));
    b.normalize?.();
  });

  if (!q) { contador.textContent = ''; _resultadosBusqueda = []; return; }

  _resultadosBusqueda = [];
  burbujas.forEach(b => {
    const idx = b.textContent.toLowerCase().indexOf(q);
    if (idx === -1) { b.classList.add('msg-atenuado'); return; }
    _resultadosBusqueda.push(b);
    // Resaltar coincidencias solo en nodos de texto (no toca el <img> ni los íconos)
    b.childNodes.forEach(nodo => {
      if (nodo.nodeType !== Node.TEXT_NODE) return;
      const texto = nodo.textContent;
      const pos = texto.toLowerCase().indexOf(q);
      if (pos === -1) return;
      const span = document.createElement('span');
      span.innerHTML = _escaparHtml(texto.slice(0, pos)) + '<mark>' + _escaparHtml(texto.slice(pos, pos + q.length)) + '</mark>' + _escaparHtml(texto.slice(pos + q.length));
      nodo.replaceWith(span);
    });
  });

  _indiceBusquedaActual = _resultadosBusqueda.length ? 0 : -1;
  _actualizarContadorYFoco(q);
}

function _actualizarContadorYFoco() {
  const contador = document.getElementById('contadorBusqueda');
  if (!_resultadosBusqueda.length) {
    contador.textContent = 'Sin resultados';
    return;
  }
  contador.textContent = `${_indiceBusquedaActual + 1} / ${_resultadosBusqueda.length}`;
  document.querySelectorAll('.msg-resaltado-actual').forEach(b => b.classList.remove('msg-resaltado-actual'));
  const actual = _resultadosBusqueda[_indiceBusquedaActual];
  if (actual) {
    actual.classList.add('msg-resaltado-actual');
    actual.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
}

function irResultadoBusqueda(direccion) {
  if (!_resultadosBusqueda.length) return;
  _indiceBusquedaActual = (_indiceBusquedaActual + direccion + _resultadosBusqueda.length) % _resultadosBusqueda.length;
  _actualizarContadorYFoco();
}
function verPerfilContacto() {
  if (!conversacionActiva) return;
  const otro = conversacionActiva.otro;
  const rolLabel = { superadmin: 'Superadmin', admin: 'Admin', usuario: 'Usuario' };

  document.getElementById('perfilContactoAvatar').innerHTML = avatarInner(otro.nombre, otro.foto_url);
  document.getElementById('perfilContactoNombre').textContent = otro.nombre;
  document.getElementById('perfilContactoRol').textContent = rolLabel[otro.rol] || otro.rol || '—';
  document.getElementById('perfilContactoCorreo').textContent = otro.correo || '—';

  const estadoEl = document.getElementById('perfilContactoEstado');
  if (otro.en_linea) {
    estadoEl.textContent = 'En línea';
    estadoEl.style.background = '#22c55e';
  } else {
    estadoEl.textContent = 'Desconectado';
    estadoEl.style.background = '#94a3b8';
  }

  new bootstrap.Modal(document.getElementById('modalPerfilContacto')).show();
}

// ════════════════════════════════════════════════════════
//  Directorio: iniciar una conversación nueva
// ════════════════════════════════════════════════════════
async function abrirDirectorio() {
  const cont = document.getElementById('listaDirectorio');
  cont.innerHTML = '<div class="text-center text-secondary py-4"><i class="bi bi-arrow-repeat me-1"></i>Cargando…</div>';
  try {
    const r = await fetchAPI(`${API}/chat/usuarios`);
    const usuarios = await r.json();
    _renderDirectorio(usuarios);
    document.getElementById('buscarDirectorioInput').oninput = async (e) => {
      const r2 = await fetchAPI(`${API}/chat/usuarios?buscar=${encodeURIComponent(e.target.value)}`);
      _renderDirectorio(await r2.json());
    };
  } catch (e) {
    cont.innerHTML = '<div class="text-center text-danger py-4 small">Error al cargar usuarios.</div>';
  }
}

function _renderDirectorio(usuarios) {
  const cont = document.getElementById('listaDirectorio');
  if (!usuarios.length) {
    cont.innerHTML = '<div class="text-center text-secondary py-4 small">Sin resultados.</div>';
    return;
  }
  const rolLabel = { superadmin: 'Superadmin', admin: 'Admin', usuario: 'Usuario' };
  cont.innerHTML = usuarios.map(u => `
    <div class="dir-item" onclick="iniciarConversacionCon(${u.id_usuario}, '${u.nombre.replace(/'/g, "\\'")}', '${(u.foto_url || '').replace(/'/g, "\\'")}')">
      <div class="conv-avatar" style="width:38px;height:38px;font-size:.78rem;">${avatarInner(u.nombre, u.foto_url)}</div>
      <div>
        <div class="fw-semibold small" style="color:#0b2340;">${u.nombre}</div>
        <div class="small text-secondary">${rolLabel[u.rol] || u.rol} · ${u.correo}</div>
      </div>
    </div>
  `).join('');
}

async function iniciarConversacionCon(idUsuario, nombre, fotoUrl = '') {
  document.activeElement?.blur();
  bootstrap.Modal.getInstance(document.getElementById('modalNuevoChat'))?.hide();
  cerrarBusquedaMensajes();

  // Si ya existe una conversación con esta persona, solo la abrimos
  const existente = conversaciones.find(c => c.otro.id_usuario === idUsuario);
  if (existente) { abrirConversacion(existente.id_conversacion); return; }

  // Si no existe, se creará al enviar el primer mensaje.
  // Mientras tanto, mostramos un chat "vacío" listo para escribir.
  conversacionActiva = {
    id_conversacion: null,
    otro: { id_usuario: idUsuario, nombre, foto_url: fotoUrl, en_linea: false },
    no_leidos: 0,
  };
  document.getElementById('chatVacio').style.display = 'none';
  document.getElementById('chatActivo').style.display = 'flex';
  document.getElementById('chatWrap').classList.add('chat-abierto-movil');
  document.getElementById('chatAvatarOtro').innerHTML = avatarInner(nombre, fotoUrl);
  document.getElementById('chatNombreOtro').textContent = nombre;
  document.getElementById('chatEstadoOtro').textContent = '';
  document.getElementById('mensajesScroll').innerHTML = '<div class="text-center text-secondary py-4 small">Aún no hay mensajes. ¡Saluda! 👋</div>';
  document.getElementById('inputMensaje').focus();
}

// ════════════════════════════════════════════════════════
//  Tiempo real con Pusher
// ════════════════════════════════════════════════════════
function iniciarPusher() {
  if (typeof Pusher === 'undefined' || PUSHER_KEY.startsWith('PON_AQUI')) return;

  pusher = new Pusher(PUSHER_KEY, {
    cluster: PUSHER_CLUSTER,
    authorizer: (channel) => ({
      authorize: async (socketId, callback) => {
        try {
          const r = await fetchAPI(`${API}/chat/pusher-auth`, {
            method: 'POST',
            body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
          });
          const data = await r.json();
          callback(null, data);
        } catch (e) {
          callback(e, null);
        }
      },
    }),
  });

  canalPrivado = pusher.subscribe(`private-usuario.${usuarioYo.id_usuario}`);

  canalPrivado.bind('nuevo-mensaje', (m) => {
    // Si es la conversación abierta ahora mismo, lo pintamos directo
    if (conversacionActiva && conversacionActiva.id_conversacion === m.id_conversacion) {
      _agregarMensajeAlChat(m);
    }
    cargarConversaciones();
    if (typeof _actualizarBadgeMensajes === 'function') _actualizarBadgeMensajes();
  });

  canalPrivado.bind('mensajes-leidos', (data) => {
    if (conversacionActiva && conversacionActiva.id_conversacion === data.id_conversacion) {
      _marcarMisMensajesComoLeidos();
    }
  });

  canalPrivado.bind('escribiendo', (data) => {
    if (conversacionActiva && conversacionActiva.otro?.id_usuario === data.id_usuario_emisor) {
      _mostrarIndicadorEscribiendo(data.nombre_emisor);
    }
  });
}

// ════════════════════════════════════════════════════════
//  Init
// ════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
  usuarioYo = getUsuario();
  document.getElementById('buscarConversacionInput').addEventListener('input', filtrarConversaciones);

  const inputBusqueda = document.getElementById('inputBuscarMensajes');
  inputBusqueda.addEventListener('input', _ejecutarBusquedaMensajes);
  inputBusqueda.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); irResultadoBusqueda(e.shiftKey ? -1 : 1); }
    if (e.key === 'Escape') cerrarBusquedaMensajes();
  });

  await cargarConversaciones();
  iniciarPusher();
});
