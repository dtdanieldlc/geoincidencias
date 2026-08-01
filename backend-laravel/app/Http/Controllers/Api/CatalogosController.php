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
        $permitidas = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
        $q = Ciudad::query()->whereIn('nombre', $permitidas);
        if (\Illuminate\Support\Facades\Schema::hasColumn('ciudades', 'activo')) {
            $q->where('activo', 1);
        }
        return $q->orderBy('nombre')
            ->get(['id_ciudad as id', 'nombre', 'latitud_ref as latitud', 'longitud_ref as longitud']);
    }

    // GET /api/catalogos/zonas?id_ciudad=5
    // Si se pasa id_ciudad, filtra las zonas internas de esa sucursal únicamente
    public function zonas(Request $request)
    {
        $permitidas = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
        $query = Zona::query()
            ->where('zonas.activo', 1)
            ->join('ciudades as c', 'c.id_ciudad', '=', 'zonas.id_ciudad')
            ->whereIn('c.nombre', $permitidas);

        if ($idCiudad = $request->query('id_ciudad')) {
            $query->where('zonas.id_ciudad', $idCiudad);
        }

        return $query->orderBy('zonas.nombre')
            ->get(['zonas.id_zona as id', 'zonas.nombre', 'zonas.id_ciudad']);
    }

    public function usuarios()
    {
        return Usuario::where('activo', 1)
            ->orderBy('nombre')
            ->where('rol', '!=', 'superadmin')
            ->selectRaw("id_usuario as id, CONCAT(nombre,' ',IFNULL(apellido,'')) as nombre, correo, rol")
            ->get();
    }

    public function departamentos()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('departamentos')) {
            return response()->json([]);
        }
        $q = \Illuminate\Support\Facades\DB::table('departamentos')->orderBy('nombre');
        if (\Illuminate\Support\Facades\Schema::hasColumn('departamentos', 'activo')) {
            $q->where('activo', 1);
        }
        return response()->json(
            $q->get(['id_departamento as id', 'nombre', 'descripcion'])
        );
    }

    public function incentivos()
    {
        return IncentivoPrioridad::all(['prioridad', 'monto']);
    }
}
