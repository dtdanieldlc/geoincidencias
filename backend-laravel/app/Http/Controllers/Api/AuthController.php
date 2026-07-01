<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            'nombre'            => 'required|string|max:100',
            'apellido'          => 'nullable|string|max:100',
            'correo'            => 'required|email|unique:usuarios,correo',
            'password'          => 'required|string|min:6',
            'telefono'          => 'nullable|string|max:20',
            'cedula'            => ['required', 'digits:10', 'unique:usuarios,cedula', function ($attribute, $value, $fail) {
                if (! self::cedulaEsValida($value)) {
                    $fail('La cédula ingresada no es válida.');
                }
            }],
            'pregunta_secreta'  => 'required|string|max:150',
            'respuesta_secreta' => 'required|string|min:2|max:150',
        ], [
            'cedula.digits' => 'La cédula debe tener 10 dígitos.',
            'cedula.unique' => 'Esa cédula ya está registrada.',
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
            'cedula'            => $request->cedula,
            'pregunta_secreta'  => $request->pregunta_secreta,
            'respuesta_secreta' => Hash::make(self::normalizarRespuesta($request->respuesta_secreta)),
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
    //  Recuperación de contraseña sin correo/servicios externos
    //  Paso 1) POST /api/auth/recuperar/pregunta   { correo, cedula }
    //  Paso 2) POST /api/auth/recuperar/verificar  { correo, cedula, respuesta_secreta }
    //  Paso 3) POST /api/auth/recuperar/reset      { token, password_nuevo }
    // ──────────────────────────────────────────────────────────────

    private static function normalizarRespuesta(string $respuesta): string
    {
        // Evita que fallos por mayúsculas/tildes/espacios bloqueen a un usuario legítimo
        $respuesta = mb_strtolower(trim($respuesta));
        $respuesta = preg_replace('/\s+/', ' ', $respuesta);
        $sinTildes = strtr($respuesta, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return $sinTildes;
    }

    private static function cedulaEsValida(string $cedula): bool
    {
        if (! preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        $provincia = (int) substr($cedula, 0, 2);
        $tercerDigito = (int) $cedula[2];

        if ($provincia < 1 || $provincia > 24 || $tercerDigito > 6) {
            return false;
        }

        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $cedula[$i] * $coeficientes[$i];
            if ($valor > 9) {
                $valor -= 9;
            }
            $suma += $valor;
        }

        $digitoVerificador = (int) $cedula[9];
        $decena = ceil($suma / 10) * 10;
        $resultado = (int) (($decena - $suma) === 10.0 ? 0 : $decena - $suma);

        return $resultado === $digitoVerificador;
    }

    // Paso 1: dado correo + cédula, devuelve la pregunta de seguridad (sin revelar si existe la cuenta con detalle)
    public function preguntaSecreta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'cedula' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo y cédula son obligatorios.'], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('cedula', $request->cedula)
            ->where('activo', 1)
            ->first();

        if (! $usuario || ! $usuario->pregunta_secreta) {
            return response()->json(['ok' => false, 'mensaje' => 'No encontramos una cuenta con esos datos.'], 404);
        }

        return response()->json([
            'ok'               => true,
            'pregunta_secreta' => $usuario->pregunta_secreta,
        ]);
    }

    // Paso 2: valida la respuesta y entrega un token temporal de un solo uso (15 min)
    public function verificarRecuperacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'            => 'required|email',
            'cedula'            => 'required|digits:10',
            'respuesta_secreta' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Completa todos los campos.'], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('cedula', $request->cedula)
            ->where('activo', 1)
            ->first();

        if (! $usuario || ! $usuario->respuesta_secreta ||
            ! Hash::check(self::normalizarRespuesta($request->respuesta_secreta), $usuario->respuesta_secreta)) {
            return response()->json(['ok' => false, 'mensaje' => 'Los datos no coinciden con nuestros registros.'], 401);
        }

        $token = bin2hex(random_bytes(32));

        $usuario->reset_token = hash('sha256', $token);
        $usuario->reset_token_expira = now()->addMinutes(15);
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'recuperacion_password_verificada',
            "Usuario verificó su identidad para restablecer contraseña", $request->ip()
        );

        return response()->json([
            'ok'    => true,
            'token' => $token,
        ]);
    }

    // Paso 3: cambia la contraseña usando el token temporal emitido en el paso 2
    public function restablecerPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'          => 'required|string',
            'password_nuevo' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $tokenHasheado = hash('sha256', $request->token);

        $usuario = Usuario::where('reset_token', $tokenHasheado)
            ->where('reset_token_expira', '>', now())
            ->first();

        if (! $usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'El enlace expiró o ya fue usado. Vuelve a verificar tu identidad.'], 401);
        }

        $usuario->password = Hash::make($request->password_nuevo);
        $usuario->reset_token = null;
        $usuario->reset_token_expira = null;
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'password_restablecida',
            "Usuario restableció su contraseña por olvido", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/auth/perfil
    // ──────────────────────────────────────────────────────────────
        public function actualizarPerfil(Request $request)
    {
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'nombre'            => 'required|string|max:100',
            'apellido'          => 'nullable|string|max:100',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'required|email|unique:usuarios,correo,' . $usuario->id_usuario . ',id_usuario',
            'cedula'            => ['nullable', 'digits:10', 'unique:usuarios,cedula,' . $usuario->id_usuario . ',id_usuario', function ($attribute, $value, $fail) {
                if ($value && ! self::cedulaEsValida($value)) {
                    $fail('La cédula ingresada no es válida.');
                }
            }],
            'pregunta_secreta'  => 'nullable|string|max:150',
            'respuesta_secreta' => 'nullable|string|min:2|max:150',
        ], [
            'cedula.digits' => 'La cédula debe tener 10 dígitos.',
            'cedula.unique' => 'Esa cédula ya está registrada.',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario->nombre   = $request->nombre;
        $usuario->apellido = $request->apellido;
        $usuario->telefono = $request->telefono;
        $usuario->correo   = $request->correo;

        // La cédula siempre se puede editar/completar
        if ($request->filled('cedula')) {
            $usuario->cedula = $request->cedula;
        }

        // La pregunta secreta solo se puede configurar UNA vez; si ya existe, se ignora cualquier intento de cambiarla aquí
        if (! $usuario->pregunta_secreta && $request->filled('pregunta_secreta') && $request->filled('respuesta_secreta')) {
            $usuario->pregunta_secreta  = $request->pregunta_secreta;
            $usuario->respuesta_secreta = Hash::make(self::normalizarRespuesta($request->respuesta_secreta));
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
}
