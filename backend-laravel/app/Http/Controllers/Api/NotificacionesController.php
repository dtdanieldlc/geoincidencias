<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index(Request $request)
    {
        $datos = Notificacion::where('id_usuario', $request->user()->id_usuario)
            ->orderByDesc('fecha')
            ->limit(30)
            ->get();

        return response()->json($datos);
    }

    public function noLeidas(Request $request)
    {
        $total = Notificacion::where('id_usuario', $request->user()->id_usuario)
            ->where('leida', 0)->count();

        return response()->json(['total' => $total]);
    }

    public function marcarLeida(Request $request, $id)
    {
        Notificacion::where('id_notificacion', $id)
            ->where('id_usuario', $request->user()->id_usuario)
            ->update(['leida' => 1]);

        return response()->json(['ok' => true]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        Notificacion::where('id_usuario', $request->user()->id_usuario)->update(['leida' => 1]);
        return response()->json(['ok' => true]);
    }
}
