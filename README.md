# GeoIncidencias — Proyecto Completo (Laravel + Frontend)

Sistema Web de Gestión de Incidencias Georreferenciadas — backend migrado a **Laravel 11**
para cumplir los lineamientos académicos.

## Estructura

```
GeoIncidencias_Laravel/
├── backend-laravel/          ← Backend en Laravel (PHP) — API REST
├── frontend/                  ← HTML + Bootstrap 5 + JS puro (fetch)
└── database-mysql-original/   ← Script .sql de referencia (mismas tablas que las migraciones)
```

## Orden de instalación

### 1. Backend Laravel

```powershell
cd backend-laravel
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Esto deja el backend corriendo en `http://127.0.0.1:8000`.
Instrucciones detalladas en `backend-laravel/README.md`.

### 2. Frontend

El frontend ya está configurado para apuntar a `http://127.0.0.1:8000/api`
(ver `frontend/js/auth-guard.js`, línea de `const API`).

Ábrelo con cualquier servidor estático (Live Server de VS Code, por ejemplo) y entra a:
```
frontend/html/login.html
```

**Importante**: si cambias el puerto donde corre Laravel (`php artisan serve --port=XXXX`),
actualiza esa misma línea en `frontend/js/auth-guard.js` y en `frontend/js/login.js`.

## Usuarios de prueba

Contraseña para todos: `123456`

| Correo | Rol |
|---|---|
| admin@geoincidencias.com | Administrador |
| cmendoza@empresa.com | Usuario |
| mgonzalez@empresa.com | Usuario |
| pramirez@empresa.com | Usuario |
| ltorres@empresa.com | Usuario |

## ¿Por qué dos versiones de base de datos?

`database-mysql-original/geoincidencias_v2.sql` es el script SQL plano (por si tu
documento técnico pide adjuntarlo como evidencia, según el punto 8 del PDF de
lineamientos: "Archivo SQL (adjunto)").

Las migraciones de Laravel (`backend-laravel/database/migrations/`) generan exactamente
las mismas tablas mediante código PHP — es la forma "Laravel" de crear la base de datos,
y es la que debes usar para que el ORM (Eloquent) funcione correctamente.

No ejecutes ambas sobre la misma base de datos al mismo tiempo — usa las migraciones de
Laravel como fuente de verdad.
