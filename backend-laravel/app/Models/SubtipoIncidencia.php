<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubtipoIncidencia extends Model
{
    protected $table = 'subtipos_incidencia';
    protected $primaryKey = 'id_subtipo';
    public $timestamps = false;
    protected $fillable = ['id_tipo', 'nombre', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function tipo()
    {
        return $this->belongsTo(TipoIncidencia::class, 'id_tipo', 'id_tipo');
    }
}
