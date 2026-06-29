<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public    $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'correo',
        'password',
        'rol',
        'telefono',
        'saldo_incentivos',
        'activo',
        'foto_url',
        // Verificación de correo
        'correo_verificado',
        'correo_verificado_at',
        'codigo_verificacion',
        'codigo_verificacion_expira',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'codigo_verificacion',
        'codigo_verificacion_expira',
    ];

    protected $casts = [
        'password'                   => 'hashed',
        'saldo_incentivos'           => 'decimal:2',
        'activo'                     => 'boolean',
        'correo_verificado'          => 'boolean',
        'correo_verificado_at'       => 'datetime',
        'codigo_verificacion_expira' => 'datetime',
        'created_at'                 => 'datetime',
    ];

    // ── Accessors ────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    // ── Relaciones ───────────────────────────────────────────────

    public function incidenciasCreadas()
    {
        return $this->hasMany(Incidencia::class, 'id_usuario_creador', 'id_usuario');
    }

    public function apoyos()
    {
        return $this->hasMany(IncidenciaApoyo::class, 'id_usuario', 'id_usuario');
    }

    // ── Canal de notificación por correo ─────────────────────────

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }
}
