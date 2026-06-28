<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaAsignacion extends Model
{
    protected $table = 'incidencia_asignaciones';
    protected $primaryKey = 'id_asignacion';
    public $timestamps = false;
    protected $fillable = ['id_incidencia', 'id_usuario', 'rol_asignacion'];
    protected $casts = ['fecha_asignacion' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia', 'id_incidencia');
    }
}
