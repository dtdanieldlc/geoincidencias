<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaApoyo extends Model
{
    protected $table = 'incidencia_apoyos';
    protected $primaryKey = 'id_apoyo';
    public $timestamps = false;

    protected $fillable = [
        'id_incidencia', 'id_usuario', 'monto_incentivo', 'estado_pago',
        'comentario_usuario', 'id_admin_revisor', 'comentario_admin',
        'fecha_revision', 'fecha_pago',
    ];

    protected $casts = [
        'monto_incentivo' => 'decimal:2',
        'fecha_apoyo' => 'datetime',
        'fecha_revision' => 'datetime',
        'fecha_pago' => 'datetime',
    ];

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia', 'id_incidencia');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function adminRevisor()
    {
        return $this->belongsTo(Usuario::class, 'id_admin_revisor', 'id_usuario');
    }
}
