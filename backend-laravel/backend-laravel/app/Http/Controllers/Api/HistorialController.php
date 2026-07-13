<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('historial_actividad as h')
            ->leftJoin('usuarios as u', 'h.id_usuario', '=', 'u.id_usuario')
            ->leftJoin('incidencias as i', 'h.id_incidencia', '=', 'i.id_incidencia');

        if ($usuario = $request->query('usuario')) $query->where('h.id_usuario', $usuario);
        if ($accion = $request->query('accion'))   $query->where('h.accion', $accion);
        if ($desde = $request->query('desde'))     $query->whereDate('h.fecha_hora', '>=', $desde);
        if ($hasta = $request->query('hasta'))      $query->whereDate('h.fecha_hora', '<=', $hasta);

        $porPagina = (int) $request->query('por_pagina', 20);
        $pagina = (int) $request->query('pagina', 1);

        $total = (clone $query)->count();

        $datos = $query->orderByDesc('h.fecha_hora')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->select([
                'h.id_actividad', 'h.accion', 'h.detalle', 'h.fecha_hora', 'h.ip_origen', 'h.id_incidencia',
                DB::raw("CONCAT(u.nombre,' ',IFNULL(u.apellido,'')) as usuario"),
                'i.titulo as incidencia_titulo',
            ])
            ->get();

        return response()->json([
            'datos' => $datos,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
        ]);
    }

    public function acciones()
    {
        $acciones = DB::table('historial_actividad')
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return response()->json($acciones);
    }
}
