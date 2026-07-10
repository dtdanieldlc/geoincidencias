<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/login
    // ──────────────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo y contraseña son obligatorios.'], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->where('activo', 1)->first();

        if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales incorrectas.'], 401);
        }

        $token = $usuario->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;

        $datosUsuario = [
            'id_usuario' => $usuario->id_usuario,
            'correo'     => $usuario->correo,
            'nombre'     => $usuario->nombre_completo,
            'rol'        => $usuario->rol,
        ];

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'login',
            "Usuario {$datosUsuario['nombre']} inició sesión", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'token'   => $token,
            'usuario' => $datosUsuario,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/registro
    // ──────────────────────────────────────────────────────────────
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'   => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'correo'   => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::create([
            'nombre'            => $request->nombre,
            'apellido'          => $request->apellido,
            'correo'            => $request->correo,
            'password'          => Hash::make($request->password),
            'rol'               => 'usuario',
            'telefono'          => $request->telefono,
            'activo'            => true,
            'correo_verificado' => true,
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'registro_usuario',
            "Nuevo usuario registrado: {$usuario->nombre} ({$usuario->correo})", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Cuenta creada. Ya puedes iniciar sesión.',
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/auth/perfil
    // ──────────────────────────────────────────────────────────────
    public function perfil(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'id_usuario'             => $usuario->id_usuario,
            'nombre'                 => $usuario->nombre,
            'apellido'               => $usuario->apellido,
            'correo'                 => $usuario->correo,
            'rol'                    => $usuario->rol,
            'telefono'               => $usuario->telefono,
            'cedula'                 => $usuario->cedula,
            'saldo_incentivos'       => $usuario->saldo_incentivos,
            'created_at'             => $usuario->created_at,
            'correo_verificado'      => $usuario->correo_verificado,
            'foto_url'               => $usuario->foto_url ? Storage::url($usuario->foto_url) : null,
            'tiene_pregunta_secreta' => !empty($usuario->pregunta_secreta),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/auth/perfil
    // ──────────────────────────────────────────────────────────────
    public function actualizarPerfil(Request $request)
    {
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'nombre'            => 'required|string|max:100',
            'apellido'          => 'nullable|string|max:100',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'required|email|unique:usuarios,correo,' . $usuario->id_usuario . ',id_usuario',
            'cedula'            => 'nullable|string|max:10',
            'pregunta_secreta'  => 'nullable|string|max:255',
            'respuesta_secreta' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario->nombre   = $request->nombre;
        $usuario->apellido = $request->apellido;
        $usuario->telefono = $request->telefono;
        $usuario->correo   = $request->correo;
        $usuario->cedula   = $request->cedula;

        // La pregunta secreta solo se puede configurar una vez (no se sobrescribe si ya existe)
        if ($request->filled('respuesta_secreta') && empty($usuario->pregunta_secreta)) {
            $usuario->pregunta_secreta  = $request->pregunta_secreta;
            $usuario->respuesta_secreta = strtolower(trim($request->respuesta_secreta)); // el cast 'hashed' la encripta al guardar
        }

        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'actualizar_perfil',
            "Usuario actualizó su perfil", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Perfil actualizado correctamente.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/auth/cambiar-password
    // ──────────────────────────────────────────────────────────────
    public function cambiarPassword(Request $request)
    {
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required|string',
            'password_nuevo'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        if (! Hash::check($request->password_actual, $usuario->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'La contraseña actual es incorrecta.'], 400);
        }

        $usuario->password = Hash::make($request->password_nuevo);
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'cambiar_password',
            "Usuario cambió su contraseña", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/foto
    // ──────────────────────────────────────────────────────────────
    public function subirFoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = $request->user();

        if ($usuario->foto_url) {
            Storage::disk('public')->delete($usuario->foto_url);
        }

        $ruta = $request->file('foto')->store('fotos_perfil', 'public');
        $usuario->foto_url = $ruta;
        $usuario->save();

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Foto actualizada correctamente.',
            'foto_url' => Storage::url($ruta),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/logout
    // ──────────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Sesión cerrada.']);
    }

    // ══════════════════════════════════════════════════════════════
    //  RECUPERACIÓN DE CONTRASEÑA (cédula + pregunta secreta)
    //  Estos 3 pasos ya estaban armados en el frontend (login.js) y
    //  en la tabla usuarios (migración add_recuperacion_cuenta), pero
    //  nunca se habían conectado en el backend — el modal "¿Olvidaste
    //  tu contraseña?" simplemente fallaba en silencio.
    // ══════════════════════════════════════════════════════════════

    // POST /api/auth/recuperar/pregunta  { correo, cedula }
    public function recuperarPregunta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'cedula' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('cedula', $request->cedula)
            ->first();

        // Mensaje genérico a propósito: no revelar si el correo existe o no.
        if (! $usuario || empty($usuario->pregunta_secreta)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No encontramos una cuenta con esos datos, o todavía no configuraste tu pregunta de seguridad en "Mi Perfil".',
            ], 404);
        }

        return response()->json(['ok' => true, 'pregunta_secreta' => $usuario->pregunta_secreta]);
    }

    // POST /api/auth/recuperar/verificar  { correo, cedula, respuesta_secreta }
    public function recuperarVerificar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'            => 'required|email',
            'cedula'            => 'required|string',
            'respuesta_secreta' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('cedula', $request->cedula)
            ->first();

        $respuesta = strtolower(trim($request->respuesta_secreta));

        if (! $usuario || empty($usuario->respuesta_secreta) || ! Hash::check($respuesta, $usuario->respuesta_secreta)) {
            return response()->json(['ok' => false, 'mensaje' => 'La respuesta no es correcta.'], 422);
        }

        // Token de un solo uso, 15 minutos de validez. Se guarda hasheado
        // (igual que una contraseña) para que ni con acceso a la BD alguien
        // pueda generar un reset válido sin haber pasado por este paso.
        $tokenPlano = Str::random(48);
        $usuario->reset_token        = hash('sha256', $tokenPlano);
        $usuario->reset_token_expira = now()->addMinutes(15);
        $usuario->save();

        return response()->json(['ok' => true, 'token' => $tokenPlano]);
    }

    // POST /api/auth/recuperar/reset  { token, password_nuevo }
    public function recuperarReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'          => 'required|string',
            'password_nuevo' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::where('reset_token', hash('sha256', $request->token))
            ->where('reset_token_expira', '>', now())
            ->first();

        if (! $usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'El enlace de recuperación expiró o no es válido. Vuelve a intentarlo.'], 422);
        }

        $usuario->password           = $request->password_nuevo; // cast 'hashed' lo encripta solo
        $usuario->reset_token        = null;
        $usuario->reset_token_expira = null;
        $usuario->save();

        // Por seguridad: cerrar cualquier sesión activa que tuviera con la contraseña anterior.
        $usuario->tokens()->delete();

        HistorialActividad::registrar($usuario->id_usuario, null, 'password_recuperado', 'Usuario recuperó su contraseña vía pregunta secreta.', $request->ip());

        return response()->json(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }
}
