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

    // Módulos disponibles en el sistema
    public static function modulosDisponibles(): array
    {
        return [
            'dashboard',
            'incidencias',
            'usuarios',
            'incentivos',
            'apoyos',
            'reportes',
            'historial',
        ];
    }
}
