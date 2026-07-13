<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Ubicación ──
        DB::table('paises')->insert(['id_pais' => 1, 'nombre' => 'Ecuador', 'codigo_iso' => 'EC']);

        DB::table('provincias')->insert([
            ['id_provincia' => 1, 'id_pais' => 1, 'nombre' => 'Guayas'],
            ['id_provincia' => 2, 'id_pais' => 1, 'nombre' => 'Pichincha'],
            ['id_provincia' => 3, 'id_pais' => 1, 'nombre' => 'Santa Elena'],
        ]);

        DB::table('ciudades')->insert([
            ['id_ciudad' => 1, 'id_provincia' => 1, 'nombre' => 'Guayaquil', 'latitud_ref' => -2.170998, 'longitud_ref' => -79.922359],
            ['id_ciudad' => 2, 'id_provincia' => 2, 'nombre' => 'Quito', 'latitud_ref' => -0.180653, 'longitud_ref' => -78.467838],
            ['id_ciudad' => 3, 'id_provincia' => 3, 'nombre' => 'La Libertad', 'latitud_ref' => -2.232450, 'longitud_ref' => -80.905610],
        ]);

        DB::table('zonas')->insert([
            ['id_ciudad' => 1, 'nombre' => 'Planta Baja', 'descripcion' => 'Area de recepcion y acceso principal', 'latitud_ref' => -2.900100, 'longitud_ref' => -79.005900, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Piso 1', 'descripcion' => 'Oficinas administrativas', 'latitud_ref' => -2.900200, 'longitud_ref' => -79.005800, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Piso 2', 'descripcion' => 'Area tecnica y sistemas', 'latitud_ref' => -2.900300, 'longitud_ref' => -79.005700, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Piso 3', 'descripcion' => 'Gerencia y salas de reuniones', 'latitud_ref' => -2.900400, 'longitud_ref' => -79.005600, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Bodega', 'descripcion' => 'Almacen y logistica', 'latitud_ref' => -2.900500, 'longitud_ref' => -79.005500, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Parqueadero', 'descripcion' => 'Zona de parqueo vehicular', 'latitud_ref' => -2.900600, 'longitud_ref' => -79.005400, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Sala de Servidores', 'descripcion' => 'Centro de datos principal', 'latitud_ref' => -2.900700, 'longitud_ref' => -79.005300, 'activo' => 1],
            ['id_ciudad' => 1, 'nombre' => 'Cafeteria', 'descripcion' => 'Area de descanso y comedor', 'latitud_ref' => -2.900800, 'longitud_ref' => -79.005200, 'activo' => 1],
        ]);

        DB::table('tipos_incidencia')->insert([
            ['id_tipo' => 1, 'nombre' => 'Infraestructura', 'descripcion' => 'Danos en instalaciones fisicas', 'icono' => 'bi-building', 'color' => '#f97316', 'activo' => 1],
            ['id_tipo' => 2, 'nombre' => 'Equipos TI', 'descripcion' => 'Fallas en hardware o software', 'icono' => 'bi-pc-display', 'color' => '#6366f1', 'activo' => 1],
            ['id_tipo' => 3, 'nombre' => 'Red y Conectividad', 'descripcion' => 'Problemas de red, internet o telefonia', 'icono' => 'bi-wifi-off', 'color' => '#3b82f6', 'activo' => 1],
            ['id_tipo' => 4, 'nombre' => 'Seguridad', 'descripcion' => 'Incidentes de seguridad fisica o digital', 'icono' => 'bi-shield-exclamation', 'color' => '#ef4444', 'activo' => 1],
            ['id_tipo' => 5, 'nombre' => 'Suministros', 'descripcion' => 'Falta o dano de materiales', 'icono' => 'bi-box-seam', 'color' => '#eab308', 'activo' => 1],
            ['id_tipo' => 6, 'nombre' => 'Servicios Basicos', 'descripcion' => 'Agua, luz, clima, aseo', 'icono' => 'bi-lightning-charge', 'color' => '#10b981', 'activo' => 1],
            ['id_tipo' => 7, 'nombre' => 'Accidentes', 'descripcion' => 'Accidentes laborales o de transito', 'icono' => 'bi-bandaid', 'color' => '#f43f5e', 'activo' => 1],
        ]);

        DB::table('subtipos_incidencia')->insert([
            ['id_tipo' => 1, 'nombre' => 'Alumbrado', 'activo' => 1],
            ['id_tipo' => 1, 'nombre' => 'Goteras y Filtraciones', 'activo' => 1],
            ['id_tipo' => 1, 'nombre' => 'Puertas y accesos', 'activo' => 1],
            ['id_tipo' => 1, 'nombre' => 'Mobiliario danado', 'activo' => 1],
            ['id_tipo' => 2, 'nombre' => 'Computador no enciende', 'activo' => 1],
            ['id_tipo' => 2, 'nombre' => 'Error de software', 'activo' => 1],
            ['id_tipo' => 2, 'nombre' => 'Impresora', 'activo' => 1],
            ['id_tipo' => 2, 'nombre' => 'Perdida de datos', 'activo' => 1],
            ['id_tipo' => 3, 'nombre' => 'Internet lento', 'activo' => 1],
            ['id_tipo' => 3, 'nombre' => 'Sin conexion', 'activo' => 1],
            ['id_tipo' => 3, 'nombre' => 'Telefonia IP', 'activo' => 1],
            ['id_tipo' => 4, 'nombre' => 'Robo', 'activo' => 1],
            ['id_tipo' => 4, 'nombre' => 'Acceso no autorizado', 'activo' => 1],
            ['id_tipo' => 4, 'nombre' => 'Camara danada', 'activo' => 1],
            ['id_tipo' => 4, 'nombre' => 'Alarma activada', 'activo' => 1],
            ['id_tipo' => 5, 'nombre' => 'Falta de insumos de oficina', 'activo' => 1],
            ['id_tipo' => 5, 'nombre' => 'Falta de equipo de proteccion', 'activo' => 1],
            ['id_tipo' => 6, 'nombre' => 'Corte de energia', 'activo' => 1],
            ['id_tipo' => 6, 'nombre' => 'Falla de climatizacion', 'activo' => 1],
            ['id_tipo' => 6, 'nombre' => 'Falta de agua', 'activo' => 1],
            ['id_tipo' => 7, 'nombre' => 'Accidente laboral', 'activo' => 1],
            ['id_tipo' => 7, 'nombre' => 'Accidente vehicular', 'activo' => 1],
        ]);

        DB::table('estados')->insert([
            ['id_estado' => 1, 'nombre' => 'Pendiente', 'descripcion' => 'Incidencia reportada, aun no atendida', 'color' => '#ef4444', 'orden' => 1, 'activo' => 1],
            ['id_estado' => 2, 'nombre' => 'En proceso', 'descripcion' => 'Incidencia siendo atendida por el responsable', 'color' => '#f59e0b', 'orden' => 2, 'activo' => 1],
            ['id_estado' => 3, 'nombre' => 'Resuelto', 'descripcion' => 'Incidencia solucionada', 'color' => '#22c55e', 'orden' => 3, 'activo' => 1],
            ['id_estado' => 4, 'nombre' => 'Cerrado', 'descripcion' => 'Incidencia verificada y cerrada oficialmente', 'color' => '#64748b', 'orden' => 4, 'activo' => 1],
        ]);

        DB::table('incentivos_prioridad')->insert([
            ['prioridad' => 'Baja', 'monto' => 5.00],
            ['prioridad' => 'Media', 'monto' => 10.00],
            ['prioridad' => 'Alta', 'monto' => 20.00],
        ]);

        $hash = Hash::make('123456');
        DB::table('usuarios')->insert([
            ['id_usuario' => 1, 'nombre' => 'Admin', 'apellido' => 'Sistema', 'correo' => 'admin@geoincidencias.com', 'password' => $hash, 'rol' => 'admin', 'telefono' => '0990000000', 'saldo_incentivos' => 0, 'activo' => 1, 'created_at' => now()],
            ['id_usuario' => 2, 'nombre' => 'Carlos', 'apellido' => 'Mendoza', 'correo' => 'cmendoza@empresa.com', 'password' => $hash, 'rol' => 'usuario', 'telefono' => '0991234567', 'saldo_incentivos' => 20.00, 'activo' => 1, 'created_at' => now()],
            ['id_usuario' => 3, 'nombre' => 'Maria', 'apellido' => 'Gonzalez', 'correo' => 'mgonzalez@empresa.com', 'password' => $hash, 'rol' => 'usuario', 'telefono' => '0992345678', 'saldo_incentivos' => 0, 'activo' => 1, 'created_at' => now()],
            ['id_usuario' => 4, 'nombre' => 'Pedro', 'apellido' => 'Ramirez', 'correo' => 'pramirez@empresa.com', 'password' => $hash, 'rol' => 'usuario', 'telefono' => '0993456789', 'saldo_incentivos' => 5.00, 'activo' => 1, 'created_at' => now()],
            ['id_usuario' => 5, 'nombre' => 'Lucia', 'apellido' => 'Torres', 'correo' => 'ltorres@empresa.com', 'password' => $hash, 'rol' => 'usuario', 'telefono' => '0994567890', 'saldo_incentivos' => 0, 'activo' => 1, 'created_at' => now()],
        ]);

        // incidencias con columnas explícitas
        DB::statement("INSERT INTO incidencias (titulo, descripcion, prioridad, id_tipo, id_subtipo, id_estado_actual, estado_aprobacion, id_admin_revisor, fecha_revision, motivo_rechazo, id_zona, latitud, longitud, direccion_texto, fecha_ocurrencia, hora_ocurrencia, fecha_resolucion, tiempo_resolucion_horas, reportante_nombre, reportante_contacto, id_usuario_creador) VALUES
            ('Falla en servidor principal', 'El servidor de base de datos no responde desde las 08:00.', 'Alta', 2, 5, 2, 'aprobada', 1, NOW(), NULL, 7, -2.900700, -79.005300, NULL, '2026-06-15', '08:15', NULL, NULL, 'Ana Suarez', '0997001122', 2),
            ('Corte de energia en piso 2', 'Se fue la luz en el ala norte del piso 2.', 'Alta', 6, 18, 2, 'aprobada', 1, NOW(), NULL, 3, -2.900200, -79.005800, NULL, '2026-06-15', '09:30', NULL, NULL, 'Luis Paredes', '0997002233', 3),
            ('Filtracion de agua en techo de bodega', 'Se detecto humedad y goteo en el techo de la bodega sector B.', 'Alta', 1, 2, 1, 'aprobada', 1, NOW(), NULL, 5, -2.900500, -79.005500, NULL, '2026-06-14', '14:00', NULL, NULL, 'Roberto Mora', '0997003344', 4),
            ('Impresora de recepcion fuera de servicio', 'La impresora HP LaserJet de recepcion muestra error de cabezal.', 'Media', 2, 7, 3, 'aprobada', 1, NOW(), NULL, 1, -2.900100, -79.005900, NULL, '2026-06-13', '16:45', NULL, NULL, 'Recepcion', '', 5),
            ('Posible fuga de gas en cocina', 'Olor extrano reportado en la cafeteria, no confirmado aun.', 'Alta', 6, 18, 1, 'pendiente_revision', NULL, NULL, NULL, 8, -2.900800, -79.005200, NULL, '2026-06-17', '07:50', NULL, NULL, 'Empleado anonimo', '', 3)
        ");

        // incidencia_asignaciones con columnas explícitas
        DB::statement("INSERT INTO incidencia_asignaciones (id_incidencia, id_usuario, rol_asignacion) VALUES
            (1, 2, 'responsable'),
            (2, 3, 'responsable'),
            (3, 4, 'responsable'),
            (4, 5, 'responsable')
        ");

        // incidencia_apoyos con columnas explícitas
        DB::statement("INSERT INTO incidencia_apoyos (id_incidencia, id_usuario, monto_incentivo, estado_pago, comentario_usuario, id_admin_revisor, comentario_admin, fecha_revision, fecha_pago) VALUES
            (1, 2, 20.00, 'pagado', 'Fui al data center a ayudar con el reinicio del servidor.', 1, 'Confirmado, asistencia verificada.', NOW(), NOW()),
            (4, 5, 5.00, 'pendiente_aprobacion', 'Voy a revisar la impresora esta tarde.', NULL, NULL, NULL, NULL)
        ");

        // incidencia_estados_historial con columnas explícitas
        DB::statement("INSERT INTO incidencia_estados_historial (id_incidencia, id_estado_anterior, id_estado_nuevo, id_usuario, comentario) VALUES
            (1, NULL, 1, 1, 'Incidencia registrada'),
            (1, 1, 2, 2, 'Tecnico asignado, revisando el servidor'),
            (4, NULL, 1, 1, 'Incidencia registrada'),
            (4, 1, 2, 5, 'Revisando cabezal de impresion'),
            (4, 2, 3, 5, 'Cabezal reemplazado, impresora funcionando')
        ");

        // incidencia_comentarios con columnas explícitas
        DB::statement("INSERT INTO incidencia_comentarios (id_incidencia, id_usuario, comentario) VALUES
            (1, 2, 'Revisando logs del servidor, parece ser un problema de memoria.'),
            (1, 1, 'Confirmado por administracion, prioridad maxima.')
        ");

        // notificaciones con columnas explícitas
        DB::statement("INSERT INTO notificaciones (id_usuario, id_incidencia, titulo, mensaje, leida) VALUES
            (2, 1, 'Incentivo pagado', 'Tu apoyo en la incidencia 1 fue aprobado y pagado: 20.00', 1),
            (5, 4, 'Apoyo registrado', 'Tu solicitud de apoyo esta pendiente de aprobacion del administrador.', 0),
            (1, 5, 'Nueva incidencia por revisar', 'Hay una incidencia nueva pendiente de aprobacion: Posible fuga de gas.', 0)
        ");

        // historial_actividad con columnas explícitas
        DB::statement("INSERT INTO historial_actividad (id_usuario, id_incidencia, accion, detalle) VALUES
            (2, 1, 'creo_incidencia', 'Usuario Carlos Mendoza registro la incidencia Falla en servidor principal'),
            (1, 1, 'aprobo_incidencia', 'Admin aprobo la incidencia 1'),
            (2, 1, 'marco_apoyo', 'Carlos Mendoza marco apoyo voluntario en incidencia 1'),
            (1, 1, 'aprobo_apoyo', 'Admin aprobo y pago el incentivo de 20.00 a Carlos Mendoza'),
            (3, 5, 'creo_incidencia', 'Usuario Maria Gonzalez registro la incidencia Posible fuga de gas en cocina'),
            (5, 4, 'marco_apoyo', 'Pedro Ramirez marco apoyo voluntario en incidencia 4, pendiente de aprobacion')
        ");
    }
}
