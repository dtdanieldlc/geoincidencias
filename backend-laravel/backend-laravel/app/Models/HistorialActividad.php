<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialActividad extends Model
{
    protected $table = 'historial_actividad';
    protected $primaryKey = 'id_actividad';
    public $timestamps = false;
    protected $fillable = ['id_usuario', 'id_incidencia', 'accion', 'detalle', 'ip_origen'];
    protected $casts = ['fecha_hora' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia', 'id_incidencia');
    }

    public static function registrar(?int $idUsuario, ?int $idIncidencia, string $accion, ?string $detalle = null, ?string $ip = null): void
    {
        try {
            self::create([
                'id_usuario' => $idUsuario,
                'id_incidencia' => $idIncidencia,
                'accion' => $accion,
                'detalle' => $detalle,
                'ip_origen' => $ip,
            ]);
        } catch (\Throwable $e) {
            // No tumbar la operación principal si falla la auditoría
            report($e);
        }
    }
}
