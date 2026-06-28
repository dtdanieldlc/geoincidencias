<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    protected $table = 'zonas';
    protected $primaryKey = 'id_zona';
    public $timestamps = false;
    protected $fillable = ['id_ciudad', 'nombre', 'descripcion', 'latitud_ref', 'longitud_ref', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'id_ciudad', 'id_ciudad');
    }

    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_zona', 'id_zona');
    }
}
