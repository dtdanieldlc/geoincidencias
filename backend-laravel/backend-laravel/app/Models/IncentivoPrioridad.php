<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncentivoPrioridad extends Model
{
    protected $table = 'incentivos_prioridad';
    protected $primaryKey = 'id_incentivo';
    public $timestamps = false;
    protected $fillable = ['prioridad', 'monto'];
    protected $casts = ['monto' => 'decimal:2'];
}
