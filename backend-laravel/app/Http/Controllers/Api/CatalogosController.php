<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estado;
use App\Models\IncentivoPrioridad;
use App\Models\SubtipoIncidencia;
use App\Models\TipoIncidencia;
use App\Models\Usuario;
use App\Models\Zona;

class CatalogosController extends Controller
{
    public function tipos()
    {
        return TipoIncidencia::where('activo', 1)
            ->orderBy('nombre')
            ->get(['id_tipo as id', 'nombre', 'icono', 'color']);
    }

    public function subtipos($id_tipo)
    {
        return SubtipoIncidencia::where('id_tipo', $id_tipo)
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get(['id_subtipo as id', 'nombre']);
    }

    public function estados()
    {
        return Estado::where('activo', 1)
            ->orderBy('orden')
            ->get(['id_estado as id', 'nombre', 'color']);
    }

    public function zonas()
    {
        return Zona::where('activo', 1)
            ->orderBy('nombre')
            ->get(['id_zona as id', 'nombre']);
    }

    public function usuarios()
    {
        return Usuario::where('activo', 1)
            ->orderBy('nombre')
            ->selectRaw("id_usuario as id, CONCAT(nombre,' ',IFNULL(apellido,'')) as nombre")
            ->get();
    }

    public function incentivos()
    {
        return IncentivoPrioridad::all(['prioridad', 'monto']);
    }
}
