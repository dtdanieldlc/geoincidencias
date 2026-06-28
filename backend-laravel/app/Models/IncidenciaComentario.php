<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaComentario extends Model
{
    protected $table = 'incidencia_comentarios';
    protected $primaryKey = 'id_comentario';
    public $timestamps = false;
    protected $fillable = ['id_incidencia', 'id_usuario', 'comentario'];
    protected $casts = ['fecha' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
