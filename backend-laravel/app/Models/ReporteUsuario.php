<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteUsuario extends Model
{
    protected $table      = 'reportes_usuario';
    protected $primaryKey = 'id_reporte';

    protected $fillable = [
        'id_usuario_reportado', 'id_usuario_reportante', 'motivo', 'descripcion',
        'estado', 'revisado_at', 'id_admin_revisor',
    ];

    protected $casts = [
        'revisado_at' => 'datetime',
    ];

    public const MOTIVOS = [
        'acoso'                     => 'Acoso u hostigamiento',
        'spam'                      => 'Spam o publicidad no deseada',
        'contenido_inapropiado'     => 'Contenido inapropiado',
        'comportamiento_sospechoso' => 'Comportamiento sospechoso',
        'otro'                      => 'Otro motivo',
    ];

    public function reportado()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reportado', 'id_usuario');
    }

    public function reportante()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reportante', 'id_usuario');
    }

    public function adminRevisor()
    {
        return $this->belongsTo(Usuario::class, 'id_admin_revisor', 'id_usuario');
    }
}
