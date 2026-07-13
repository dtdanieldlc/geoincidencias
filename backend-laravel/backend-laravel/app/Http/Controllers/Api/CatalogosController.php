<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estado;
use App\Models\IncentivoPrioridad;
use App\Models\SubtipoIncidencia;
use App\Models\TipoIncidencia;
use App\Models\Usuario;
use App\Models\Zona;
use App\Models\Ciudad;
use Illuminate\Http\Request;

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

    // GET /api/catalogos/sucursales
    // Devuelve las sucursales (ciudades) activas para elegir al registrar una incidencia
    public function sucursales()
    {
        return Ciudad::orderBy('nombre')
            ->get(['id_ciudad as id', 'nombre', 'latitud_ref as latitud', 'longitud_ref as longitud']);
    }

    // GET /api/catalogos/zonas?id_ciudad=5
    // Si se pasa id_ciudad, filtra las zonas internas de esa sucursal únicamente
    public function zonas(Request $request)
    {
        $query = Zona::where('activo', 1);

        if ($idCiudad = $request->query('id_ciudad')) {
            $query->where('id_ciudad', $idCiudad);
        }

        return $query->orderBy('nombre')->get(['id_zona as id', 'nombre', 'id_ciudad']);
    }

    public function usuarios()
    {
        return Usuario::where('activo', 1)
            ->orderBy('nombre')
            ->where('rol', '!=', 'superadmin')
            ->selectRaw("id_usuario as id, CONCAT(nombre,' ',IFNULL(apellido,'')) as nombre, correo, rol")
            ->get();
    }

    public function incentivos()
    {
        return IncentivoPrioridad::all(['prioridad', 'monto']);
    }
}
