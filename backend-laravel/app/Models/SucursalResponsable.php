<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SucursalResponsable extends Model
{
    protected $table      = 'sucursal_responsables';
    protected $primaryKey = 'id_asignacion';

    protected $fillable = ['id_ciudad', 'id_usuario'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
