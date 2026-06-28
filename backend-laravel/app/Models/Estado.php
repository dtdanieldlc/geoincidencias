<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estados';
    protected $primaryKey = 'id_estado';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'color', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
