<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Usuario;
use App\Services\PusherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /* ══════════════════════════════════════════════════════
       GET /api/chat/usuarios
       Directorio de personas con quien se puede iniciar un chat
       (todos los roles, cualquiera con cualquiera).
    ══════════════════════════════════════════════════════ */
    public function usuarios(Request $request)
    {
        $yo = $request->user();

        $query = Usuario::query()
            ->where('id_usuario', '!=', $yo->id_usuario)
            ->where('activo', true)
            ->select(['id_usuario', 'nombre', 'apellido', 'correo', 'rol', 'foto_url']);

        if ($buscar = $request->query('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('correo', 'like', "%{$buscar}%");
            });
        }

        $lista = $query->orderBy('nombre')->limit(100)->get()->map(fn ($u) => [
            'id_usuario' => $u->id_usuario,
            'nombre'     => trim($u->nombre . ' ' . ($u->apellido ?? '')),
            'correo'     => $u->correo,
            'rol'        => $u->rol,
            'foto_url'   => $u->foto_url ? Storage::url($u->foto_url) : null,
        ]);

        return response()->json($lista);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/chat/conversaciones
       Lista de conversaciones del usuario logueado, con el otro
       participante, el último mensaje y cuántos sin leer.
    ══════════════════════════════════════════════════════ */
    public function conversaciones(Request $request)
    {
        $yo = $request->user();

        $conversaciones = Conversacion::query()
            ->where('id_usuario_uno', $yo->id_usuario)
            ->orWhere('id_usuario_dos', $yo->id_usuario)
            ->orderByDesc('ultimo_mensaje_at')
            ->get();

        $datos = $conversaciones->map(function (Conversacion $c) use ($yo) {
            $idOtro = $c->otroParticipante($yo->id_usuario);
            $otro   = Usuario::select(['id_usuario', 'nombre', 'apellido', 'correo', 'rol', 'foto_url', 'ultima_presencia_at'])
                ->find($idOtro);

            $noLeidos = Mensaje::where('id_conversacion', $c->id_conversacion)
                ->where('id_usuario_emisor', $idOtro)
                ->whereNull('leido_at')
                ->count();

            return [
                'id_conversacion'    => $c->id_conversacion,
                'otro'               => $otro ? [
                    'id_usuario' => $otro->id_usuario,
                    'nombre'     => trim($otro->nombre . ' ' . ($otro->apellido ?? '')),
                    'correo'     => $otro->correo,
                    'rol'        => $otro->rol,
                    'foto_url'   => $otro->foto_url ? Storage::url($otro->foto_url) : null,
                    'en_linea'   => $otro->ultima_presencia_at && $otro->ultima_presencia_at->gt(now()->subMinutes(3)),
                ] : null,
                'ultimo_mensaje'     => $c->ultimo_mensaje_texto,
                'ultimo_mensaje_at'  => $c->ultimo_mensaje_at,
                'ultimo_mensaje_mio' => $c->ultimo_mensaje_id_usuario === $yo->id_usuario,
                'no_leidos'          => $noLeidos,
            ];
        })->filter(fn ($c) => $c['otro'] !== null)->values();

        return response()->json($datos);
    }

    /* ══════════════════════════════════════════════════════
       GET /api/chat/conversaciones/{id}/mensajes
    ══════════════════════════════════════════════════════ */
    public function mensajes(Request $request, int $id)
    {
        $yo = $request->user();
        $conversacion = Conversacion::findOrFail($id);

        if (! in_array($yo->id_usuario, [$conversacion->id_usuario_uno, $conversacion->id_usuario_dos])) {
            return response()->json(['ok' => false, 'mensaje' => 'No tienes acceso a esta conversación.'], 403);
        }

        $mensajes = Mensaje::where('id_conversacion', $id)
            ->orderBy('created_at')
            ->limit(300)
            ->get(['id_mensaje', 'id_usuario_emisor', 'contenido', 'tipo', 'imagen_url', 'leido_at', 'created_at'])
            ->map(fn ($m) => [
                'id_mensaje'        => $m->id_mensaje,
                'id_usuario_emisor' => $m->id_usuario_emisor,
                'contenido'         => $m->contenido,
                'tipo'              => $m->tipo,
                'imagen_url'        => $m->imagen_url ? Storage::url($m->imagen_url) : null,
                'leido_at'          => $m->leido_at,
                'created_at'        => $m->created_at,
            ]);

        // Marcar como leídos los mensajes que me mandó el otro
        $idOtro = $conversacion->otroParticipante($yo->id_usuario);
        $huboNuevosLeidos = Mensaje::where('id_conversacion', $id)
            ->where('id_usuario_emisor', '!=', $yo->id_usuario)
            ->whereNull('leido_at')
            ->exists();

        if ($huboNuevosLeidos) {
            Mensaje::where('id_conversacion', $id)
                ->where('id_usuario_emisor', '!=', $yo->id_usuario)
                ->whereNull('leido_at')
                ->update(['leido_at' => now()]);

            // Avisar al OTRO (quien mandó esos mensajes) que ya se los leyeron,
            // para que le aparezca el doble check azul en tiempo real.
            (new PusherService())->trigger(
                ["private-usuario.{$idOtro}"],
                'mensajes-leidos',
                ['id_conversacion' => (int) $id, 'leido_por' => $yo->id_usuario]
            );
        }

        return response()->json($mensajes);
    }

    /* ══════════════════════════════════════════════════════
       POST /api/chat/mensajes   { id_usuario_destino, contenido }
       Busca o crea la conversación, guarda el mensaje y lo
       transmite en tiempo real por Pusher a ambos participantes.
    ══════════════════════════════════════════════════════ */
    public function enviar(Request $request)
    {
        $yo = $request->user();

        $validator = Validator::make($request->all(), [
            'id_usuario_destino' => 'required|integer|exists:usuarios,id_usuario',
            'contenido'          => 'required|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Datos inválidos.', 'errores' => $validator->errors()], 400);
        }

        $idDestino = (int) $request->id_usuario_destino;
        if ($idDestino === $yo->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes enviarte un mensaje a ti mismo.'], 400);
        }

        $conversacion = Conversacion::entre($yo->id_usuario, $idDestino);

        $mensaje = Mensaje::create([
            'id_conversacion'   => $conversacion->id_conversacion,
            'id_usuario_emisor' => $yo->id_usuario,
            'contenido'         => trim($request->contenido),
            'tipo'              => 'texto',
        ]);

        $conversacion->update([
            'ultimo_mensaje_texto'      => \Illuminate\Support\Str::limit($mensaje->contenido, 120),
            'ultimo_mensaje_id_usuario' => $yo->id_usuario,
            'ultimo_mensaje_at'         => $mensaje->created_at,
        ]);

        $payload = [
            'id_mensaje'        => $mensaje->id_mensaje,
            'id_conversacion'   => $conversacion->id_conversacion,
            'id_usuario_emisor' => $yo->id_usuario,
            'nombre_emisor'     => trim($yo->nombre . ' ' . ($yo->apellido ?? '')),
            'contenido'         => $mensaje->contenido,
            'tipo'              => 'texto',
            'imagen_url'        => null,
            'created_at'        => $mensaje->created_at,
        ];

        // Solo se transmite al destinatario: el emisor ya pinta su propio
        // mensaje localmente al enviarlo, así que si también nos suscribiéramos
        // a nuestro propio canal, terminaríamos mostrando el mensaje duplicado.
        (new PusherService())->trigger(
            ["private-usuario.{$idDestino}"],
            'nuevo-mensaje',
            $payload
        );

        return response()->json(['ok' => true, 'mensaje' => $payload]);
    }

    /* ══════════════════════════════════════════════════════
       POST /api/chat/mensajes/imagen  (multipart/form-data)
       { id_usuario_destino, imagen }
    ══════════════════════════════════════════════════════ */
    public function enviarImagen(Request $request)
    {
        $yo = $request->user();

        $validator = Validator::make($request->all(), [
            'id_usuario_destino' => 'required|integer|exists:usuarios,id_usuario',
            'imagen'             => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $idDestino = (int) $request->id_usuario_destino;
        if ($idDestino === $yo->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No puedes enviarte un mensaje a ti mismo.'], 400);
        }

        $conversacion = Conversacion::entre($yo->id_usuario, $idDestino);
        $ruta = $request->file('imagen')->store('chat_imagenes', 'public');

        $mensaje = Mensaje::create([
            'id_conversacion'   => $conversacion->id_conversacion,
            'id_usuario_emisor' => $yo->id_usuario,
            'contenido'         => '📷 Imagen',
            'tipo'              => 'imagen',
            'imagen_url'        => $ruta,
        ]);

        $conversacion->update([
            'ultimo_mensaje_texto'      => '📷 Imagen',
            'ultimo_mensaje_id_usuario' => $yo->id_usuario,
            'ultimo_mensaje_at'         => $mensaje->created_at,
        ]);

        $payload = [
            'id_mensaje'        => $mensaje->id_mensaje,
            'id_conversacion'   => $conversacion->id_conversacion,
            'id_usuario_emisor' => $yo->id_usuario,
            'nombre_emisor'     => trim($yo->nombre . ' ' . ($yo->apellido ?? '')),
            'contenido'         => $mensaje->contenido,
            'tipo'              => 'imagen',
            'imagen_url'        => Storage::url($ruta),
            'created_at'        => $mensaje->created_at,
        ];

        (new PusherService())->trigger(
            ["private-usuario.{$idDestino}"],
            'nuevo-mensaje',
            $payload
        );

        return response()->json(['ok' => true, 'mensaje' => $payload]);
    }

    /* ══════════════════════════════════════════════════════
       POST /api/chat/escribiendo   { id_usuario_destino }
       Avisa al destinatario, en tiempo real, que le estoy
       escribiendo (se dispara con debounce desde el frontend).
    ══════════════════════════════════════════════════════ */
    public function escribiendo(Request $request)
    {
        $yo = $request->user();
        $idDestino = (int) $request->input('id_usuario_destino');
        if (! $idDestino) return response()->json(['ok' => false], 400);

        (new PusherService())->trigger(
            ["private-usuario.{$idDestino}"],
            'escribiendo',
            ['id_usuario_emisor' => $yo->id_usuario, 'nombre_emisor' => trim($yo->nombre . ' ' . ($yo->apellido ?? ''))]
        );

        return response()->json(['ok' => true]);
    }

    /* ══════════════════════════════════════════════════════
       POST /api/chat/pusher-auth   { socket_id, channel_name }
       Autoriza al usuario logueado a suscribirse a SU PROPIO
       canal privado únicamente (private-usuario.{su_id}).
    ══════════════════════════════════════════════════════ */
    public function pusherAuth(Request $request)
    {
        $yo = $request->user();
        $canal = (string) $request->input('channel_name');

        if ($canal !== "private-usuario.{$yo->id_usuario}") {
            return response()->json(['ok' => false, 'mensaje' => 'No autorizado para este canal.'], 403);
        }

        $auth = (new PusherService())->authChannel(
            (string) $request->input('socket_id'),
            $canal
        );

        return response()->json($auth);
    }
}
