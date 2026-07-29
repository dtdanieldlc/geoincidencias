<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use App\Models\HistorialActividad;
use App\Models\SucursalResponsable;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SucursalResponsableController extends Controller
{
    // GET /api/admin/sucursales-responsables
    // Lista todas las sucursales (ciudades) con sus responsables asignados.
    public function index()
    {
        $sucursales = Ciudad::with('provincia:id_provincia,nombre')
            ->orderBy('nombre')
            ->get(['id_ciudad', 'id_provincia', 'nombre', 'latitud_ref', 'longitud_ref']);

        $asignaciones = SucursalResponsable::with('usuario:id_usuario,nombre,apellido,correo,rol')->get();

        $datos = $sucursales->map(function ($s) use ($asignaciones) {
            $responsables = $asignaciones->where('id_ciudad', $s->id_ciudad)->map(fn ($a) => [
                'id_asignacion' => $a->id_asignacion,
                'id_usuario'    => $a->id_usuario,
                'nombre'        => $a->usuario ? trim($a->usuario->nombre . ' ' . ($a->usuario->apellido ?? '')) : 'Usuario eliminado',
                'correo'        => $a->usuario->correo ?? null,
                'rol'           => $a->usuario->rol ?? null,
            ])->values();

            return [
                'id_ciudad'    => $s->id_ciudad,
                'nombre'       => $s->nombre,
                'ciudad'       => $s->nombre,
                'provincia'    => $s->provincia->nombre ?? null,
                'latitud'      => $s->latitud_ref,
                'longitud'     => $s->longitud_ref,
                'responsables' => $responsables,
            ];
        });

        return response()->json($datos);
    }

    // GET /api/admin/sucursales-responsables/candidatos
    // Lista admins/superadmins disponibles para asignar como encargados.
    public function candidatos()
    {
        $lista = Usuario::where('activo', true)
            ->whereIn('rol', ['admin', 'superadmin'])
            ->orderBy('nombre')
            ->get(['id_usuario', 'nombre', 'apellido', 'correo', 'rol'])
            ->map(fn ($u) => [
                'id_usuario' => $u->id_usuario,
                'nombre'     => trim($u->nombre . ' ' . ($u->apellido ?? '')),
                'correo'     => $u->correo,
                'rol'        => $u->rol,
            ]);

        return response()->json($lista);
    }

    // POST /api/admin/sucursales-responsables   { id_ciudad, id_usuario }
    public function asignar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_ciudad'  => 'required|integer|exists:ciudades,id_ciudad',
            'id_usuario' => 'required|integer|exists:usuarios,id_usuario',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Datos inválidos.'], 400);
        }

        $usuario = Usuario::find($request->id_usuario);
        if (! $usuario || ! in_array($usuario->rol, ['admin', 'superadmin'], true)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Solo se pueden asignar administradores como encargados de sucursal.',
            ], 400);
        }

        $yaExiste = SucursalResponsable::where('id_ciudad', $request->id_ciudad)
            ->where('id_usuario', $request->id_usuario)->exists();
        if ($yaExiste) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese empleado ya es responsable de esta sucursal.'], 409);
        }

        SucursalResponsable::create([
            'id_ciudad'  => $request->id_ciudad,
            'id_usuario' => $request->id_usuario,
        ]);

        HistorialActividad::registrar(
            $request->user()->id_usuario, null, 'asignar_responsable_sucursal',
            "Se asignó un responsable a la sucursal #{$request->id_ciudad}", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Responsable asignado.']);
    }

    // DELETE /api/admin/sucursales-responsables/{idAsignacion}
    public function quitar(int $idAsignacion)
    {
        $asignacion = SucursalResponsable::findOrFail($idAsignacion);
        $asignacion->delete();

        return response()->json(['ok' => true, 'mensaje' => 'Responsable quitado.']);
    }
}
