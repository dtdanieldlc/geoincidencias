<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaFoto extends Model
{
    protected $table = 'incidencia_fotos';
    protected $primaryKey = 'id_foto';
    public $timestamps = false;
    protected $fillable = ['id_incidencia', 'id_usuario', 'ruta', 'tipo', 'fecha'];
    protected $casts = ['fecha' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia', 'id_incidencia');
    }
}
