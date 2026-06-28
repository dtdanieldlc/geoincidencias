<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incidencia extends Model
{
    protected $table = 'incidencias';
    protected $primaryKey = 'id_incidencia';
    public $timestamps = false;

    protected $fillable = [
        'titulo', 'descripcion', 'prioridad',
        'id_tipo', 'id_subtipo', 'id_estado_actual',
        'estado_aprobacion', 'id_admin_revisor', 'fecha_revision', 'motivo_rechazo',
        'id_zona', 'latitud', 'longitud', 'direccion_texto',
        'fecha_ocurrencia', 'hora_ocurrencia', 'fecha_resolucion', 'tiempo_resolucion_horas',
        'reportante_nombre', 'reportante_contacto', 'id_usuario_creador',
    ];

    protected $casts = [
        'latitud' => 'decimal:6',
        'longitud' => 'decimal:6',
        'fecha_ocurrencia' => 'date',
        'fecha_resolucion' => 'datetime',
        'fecha_revision' => 'datetime',
        'fecha_registro' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    // ── Relaciones ──
    public function tipo()
    {
        return $this->belongsTo(TipoIncidencia::class, 'id_tipo', 'id_tipo');
    }

    public function subtipo()
    {
        return $this->belongsTo(SubtipoIncidencia::class, 'id_subtipo', 'id_subtipo');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado_actual', 'id_estado');
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'id_zona', 'id_zona');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_creador', 'id_usuario');
    }

    public function adminRevisor()
    {
        return $this->belongsTo(Usuario::class, 'id_admin_revisor', 'id_usuario');
    }

    public function asignaciones()
    {
        return $this->hasMany(IncidenciaAsignacion::class, 'id_incidencia', 'id_incidencia');
    }

    public function apoyos()
    {
        return $this->hasMany(IncidenciaApoyo::class, 'id_incidencia', 'id_incidencia');
    }

    public function historialEstados()
    {
        return $this->hasMany(IncidenciaEstadoHistorial::class, 'id_incidencia', 'id_incidencia');
    }

    public function comentarios()
    {
        return $this->hasMany(IncidenciaComentario::class, 'id_incidencia', 'id_incidencia');
    }
}
