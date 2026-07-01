<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudPermiso extends Model
{
    protected $table = 'solicitudes_permisos';

    protected $fillable = [
        'id_admin_solicitante',
        'id_usuario_objetivo',
        'permisos_solicitados',
        'motivo',
        'estado',
        'respuesta_superadmin',
        'permisos_aprobados',
        'revisado_por',
    ];

    protected $casts = [
        'permisos_solicitados' => 'array',
        'permisos_aprobados'   => 'array',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'id_admin_solicitante', 'id_usuario');
    }

    public function usuarioObjetivo()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_objetivo', 'id_usuario');
    }

    public function revisadoPor()
    {
        return $this->belongsTo(Usuario::class, 'revisado_por', 'id_usuario');
    }
}
