// server.js — servidor estático del frontend de DomusCenter
// Mantiene EXACTAMENTE la misma estructura de carpetas que ya tenías
// dentro del backend (frontend/html + frontend/img), así que ningún
// archivo .html/.js necesitó cambios de rutas relativas.

const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// Sirve todo lo que hay dentro de /frontend (html/ e img/) tal cual estaba.
app.use(express.static(path.join(__dirname, 'frontend')));

// La raíz del sitio manda directo al login, igual que hacía antes el
// backend Laravel (routes/web.php).
app.get('/', (req, res) => {
  res.redirect('/html/login.html');
});

app.listen(PORT, () => {
  console.log(`Frontend DomusCenter escuchando en el puerto ${PORT}`);
});
