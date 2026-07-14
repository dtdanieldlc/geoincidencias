<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversacion extends Model
{
    protected $table      = 'conversaciones';
    protected $primaryKey = 'id_conversacion';

    protected $fillable = [
        'id_usuario_uno', 'id_usuario_dos',
        'ultimo_mensaje_texto', 'ultimo_mensaje_id_usuario', 'ultimo_mensaje_at',
    ];

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function usuarioUno()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_uno', 'id_usuario');
    }

    public function usuarioDos()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_dos', 'id_usuario');
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class, 'id_conversacion', 'id_conversacion');
    }

    // Devuelve el "otro" participante visto desde $idUsuario
    public function otroParticipante(int $idUsuario): int
    {
        return $this->id_usuario_uno === $idUsuario ? $this->id_usuario_dos : $this->id_usuario_uno;
    }

    // Busca (o crea) la conversación 1 a 1 entre dos usuarios, sin importar el orden
    public static function entre(int $idA, int $idB): self
    {
        $uno = min($idA, $idB);
        $dos = max($idA, $idB);

        return static::firstOrCreate([
            'id_usuario_uno' => $uno,
            'id_usuario_dos' => $dos,
        ]);
    }
}
