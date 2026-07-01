<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\AdminPermiso;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps    = false;

    protected $fillable = [
        'nombre', 'apellido', 'correo', 'password', 'rol',
        'telefono', 'saldo_incentivos', 'activo',
        'correo_verificado',
        'ultima_presencia_at', 'ultima_pagina',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'           => 'hashed',
        'saldo_incentivos'   => 'decimal:2',
        'activo'             => 'boolean',
        'correo_verificado'  => 'boolean',
        'ultima_presencia_at'=> 'datetime',
        'created_at'         => 'datetime',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    public function esAdmin(): bool
    {
        return in_array($this->rol, ['admin', 'superadmin']);
    }

    public function esSuperAdmin(): bool
    {
        return $this->rol === 'superadmin';
    }

    // Devuelve los permisos del admin para un módulo específico
    public function permisoEn(string $modulo): ?AdminPermiso
    {
        return AdminPermiso::where('id_usuario', $this->id_usuario)
            ->where('modulo', $modulo)
            ->first();
    }

    // Verifica si el admin puede realizar una acción en un módulo
    public function puedeEn(string $modulo, string $accion = 'ver'): bool
    {
        if ($this->esSuperAdmin()) return true;
        if ($this->rol !== 'admin') return false;
        $permiso = $this->permisoEn($modulo);
        if (!$permiso) return false;
        return match($accion) {
            'ver'      => $permiso->puede_ver,
            'editar'   => $permiso->puede_editar,
            'eliminar' => $permiso->puede_eliminar,
            default    => false,
        };
    }

    // Devuelve todos los permisos del admin como array indexado por módulo
    public function todosLosPermisos(): array
    {
        return AdminPermiso::where('id_usuario', $this->id_usuario)
            ->get()
            ->keyBy('modulo')
            ->toArray();
    }
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
