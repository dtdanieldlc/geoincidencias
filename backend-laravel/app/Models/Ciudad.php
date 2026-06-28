<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table = 'ciudades';
    protected $primaryKey = 'id_ciudad';
    public $timestamps = false;
    protected $fillable = ['id_provincia', 'nombre', 'latitud_ref', 'longitud_ref'];

    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'id_provincia', 'id_provincia');
    }

    public function zonas()
    {
        return $this->hasMany(Zona::class, 'id_ciudad', 'id_ciudad');
    }
}
