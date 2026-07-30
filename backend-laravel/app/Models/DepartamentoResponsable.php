<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartamentoResponsable extends Model
{
    protected $table = 'departamento_responsables';
    protected $primaryKey = 'id_asignacion';
    protected $fillable = ['id_departamento', 'id_usuario'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento', 'id_departamento');
    }
}
