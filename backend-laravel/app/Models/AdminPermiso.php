<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPermiso extends Model
{
    protected $table   = 'admin_permisos';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario', 'modulo',
        'puede_ver', 'puede_editar', 'puede_eliminar',
        'otorgado_por',
    ];

    protected $casts = [
        'puede_ver'      => 'boolean',
        'puede_editar'   => 'boolean',
        'puede_eliminar' => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function otorgadoPor()
    {
        return $this->belongsTo(Usuario::class, 'otorgado_por', 'id_usuario');
    }

    // Módulos disponibles en el sistema — solo los que realmente se
    // verifican con middleware `permiso:` en routes/api.php. 'dashboard' y
    // 'reportes' son páginas generales abiertas a cualquier usuario logueado
    // (ningún endpoint las restringe), y 'apoyos' era un módulo duplicado de
    // 'incentivos' que ninguna ruta llegó a comprobar nunca.
    public static function modulosDisponibles(): array
    {
        return [
            'incidencias',
            'usuarios',
            'incentivos',
            'historial',
        ];
    }

    // Acciones que realmente tienen una ruta protegida detrás, por módulo.
    // Usado por el frontend para no mostrar checkboxes de "editar/eliminar"
    // que no corresponden a ninguna acción real del sistema.
    public static function accionesPorModulo(): array
    {
        return [
            'incidencias' => ['ver', 'editar', 'eliminar'],
            'usuarios'    => ['ver', 'editar'],           // no hay ruta de admin para eliminar usuarios
            'incentivos'  => ['ver', 'editar'],           // no hay ruta para eliminar incentivos/apoyos
            'historial'   => ['ver'],                     // es un log de auditoría: no se edita ni se borra
        ];
    }
}