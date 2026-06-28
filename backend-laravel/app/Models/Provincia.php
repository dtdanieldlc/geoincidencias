<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provincia extends Model
{
    protected $table = 'provincias';
    protected $primaryKey = 'id_provincia';
    public $timestamps = false;
    protected $fillable = ['id_pais', 'nombre'];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'id_pais');
    }

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class, 'id_provincia', 'id_provincia');
    }
}
