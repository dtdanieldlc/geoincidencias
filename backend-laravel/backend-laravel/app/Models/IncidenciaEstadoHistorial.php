<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaEstadoHistorial extends Model
{
    protected $table = 'incidencia_estados_historial';
    protected $primaryKey = 'id_historial';
    public $timestamps = false;
    protected $fillable = ['id_incidencia', 'id_estado_anterior', 'id_estado_nuevo', 'id_usuario', 'comentario'];
    protected $casts = ['fecha_cambio' => 'datetime'];

    public function estadoNuevo()
    {
        return $this->belongsTo(Estado::class, 'id_estado_nuevo', 'id_estado');
    }

    public function estadoAnterior()
    {
        return $this->belongsTo(Estado::class, 'id_estado_anterior', 'id_estado');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
