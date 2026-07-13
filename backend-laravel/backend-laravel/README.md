# GeoIncidencias — Backend Laravel (API REST)

Backend migrado de Node/Express a **Laravel 11**, cumpliendo el requisito de los
lineamientos del proyecto académico. Misma lógica de negocio, mismas tablas,
mismas rutas — ahora en PHP.

## Requisitos previos

Necesitas instalar en tu PC:

1. **PHP 8.2+** — https://windows.php.net/download/ (o usa XAMPP: https://www.apachefriends.org/)
2. **Composer** — https://getcomposer.org/Composer-Setup.exe

Verifica con:
```powershell
php -v
composer -V
```

## Instalación

1. Descomprime este proyecto en tu carpeta de trabajo.

2. Instala las dependencias PHP:
```powershell
cd laravel-backend
composer install
```

3. Copia el archivo de entorno (ya viene con tus datos de site4now):
```powershell
copy .env.example .env
```

4. Genera la clave de la aplicación (obligatorio en Laravel):
```powershell
php artisan key:generate
```

5. Verifica que `.env` tenga tus datos correctos de MySQL:
```
DB_HOST=MYSQL5044.site4now.net
DB_PORT=3306
DB_DATABASE=db_acaba9_dtdlc06
DB_USERNAME=MYSQL5044.site4now.net
DB_PASSWORD=danieldt0602
```

6. Crea las tablas en tu base de datos:
```powershell
php artisan migrate
```

7. Carga los datos de ejemplo (usuarios, tipos, zonas, incidencias demo):
```powershell
php artisan db:seed
```

8. Arranca el servidor:
```powershell
php artisan serve
```

Deberías ver:
```
Server running on [http://127.0.0.1:8000]
```

## Probar que funciona

Abre en el navegador:
```
http://127.0.0.1:8000/api/health
```

Debe responder: `{"ok":true,"mensaje":"Backend funcionando correctamente."}`

## Conectar el frontend existente

El frontend (HTML + Bootstrap + JS) que ya tienes apunta a `/api/...` como ruta relativa.
Si lo sirves desde un puerto distinto (ej. Live Server en 5500) tendrás problemas de CORS
con cookies/sesión. Lo más simple:

**Opción recomendada:** copia la carpeta `frontend/` dentro de `laravel-backend/public/`,
y cambia las rutas `../js/...` de los HTML para que apunten correctamente, o sirve el
frontend con cualquier servidor estático apuntando su `API` (en `auth-guard.js`) a:
```js
const API = 'http://127.0.0.1:8000/api';
```

## Usuarios de prueba

Contraseña para todos: `123456`

| Correo | Rol |
|---|---|
| admin@geoincidencias.com | Administrador |
| cmendoza@empresa.com | Usuario |
| mgonzalez@empresa.com | Usuario |
| pramirez@empresa.com | Usuario |
| ltorres@empresa.com | Usuario |

## Diferencias clave respecto a la versión Node

- **Autenticación**: JWT manual (Node) → **Laravel Sanctum** (tokens API estándar de Laravel).
- **ORM**: queries SQL crudas con `mysql2` → **Eloquent ORM** (modelos PHP con relaciones).
- **Validación**: validación manual → `Validator` de Laravel con reglas declarativas.
- **Rutas**: `express.Router()` → `routes/api.php` con sintaxis de Laravel.
- **Middleware de rol admin**: `middlewares/auth.js` → `app/Http/Middleware/SoloAdmin.php`.

La base de datos, las reglas de negocio (incentivos por prioridad, aprobación de
incidencias, historial de auditoría) son exactamente las mismas.

## Estructura

```
laravel-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/   ← lógica de cada endpoint
│   │   └── Middleware/        ← SoloAdmin.php
│   └── Models/                ← Eloquent: Usuario, Incidencia, etc.
├── bootstrap/
│   └── app.php                ← configuración central (Laravel 11)
├── config/                    ← database, auth, sanctum, cors...
├── database/
│   ├── migrations/            ← equivalentes a las CREATE TABLE
│   └── seeders/                ← datos de ejemplo
├── routes/
│   └── api.php                ← todas las rutas /api/...
├── public/
│   └── index.php              ← punto de entrada
├── artisan                     ← CLI de Laravel
├── composer.json
└── .env.example
```
