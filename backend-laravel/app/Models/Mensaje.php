<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table      = 'mensajes';
    protected $primaryKey = 'id_mensaje';

    protected $fillable = [
        'id_conversacion', 'id_usuario_emisor', 'contenido', 'tipo', 'imagen_url', 'leido_at',
    ];

    protected $casts = [
        'leido_at' => 'datetime',
    ];

    public function emisor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_emisor', 'id_usuario');
    }

    public function conversacion()
    {
        return $this->belongsTo(Conversacion::class, 'id_conversacion', 'id_conversacion');
    }
}
