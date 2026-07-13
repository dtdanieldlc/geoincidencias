<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    protected $table = 'paises';
    protected $primaryKey = 'id_pais';
    public $timestamps = false;
    protected $fillable = ['nombre', 'codigo_iso'];

    public function provincias()
    {
        return $this->hasMany(Provincia::class, 'id_pais', 'id_pais');
    }
}
