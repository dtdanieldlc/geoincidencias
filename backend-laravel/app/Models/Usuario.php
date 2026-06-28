<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'apellido', 'correo', 'password', 'rol',
        'telefono', 'saldo_incentivos', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'saldo_incentivos' => 'decimal:2',
        'activo' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function incidenciasCreadas()
    {
        return $this->hasMany(Incidencia::class, 'id_usuario_creador', 'id_usuario');
    }

    public function apoyos()
    {
        return $this->hasMany(IncidenciaApoyo::class, 'id_usuario', 'id_usuario');
    }
}
