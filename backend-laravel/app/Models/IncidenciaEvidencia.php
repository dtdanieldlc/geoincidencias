<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaEvidencia extends Model
{
    protected $table      = 'incidencia_evidencias';
    protected $primaryKey = 'id_evidencia';

    protected $fillable = ['id_incidencia', 'id_usuario', 'tipo', 'archivo_url', 'comentario'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
