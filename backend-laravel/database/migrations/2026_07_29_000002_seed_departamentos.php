<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Departamentos base de DomusCenter.
 * Pensados para una empresa multi-sucursal que centraliza
 * registro, asignación y resolución de incidencias operativas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        $departamentos = [
            [
                'nombre'      => 'Mantenimiento',
                'descripcion' => 'Averías de infraestructura, equipos, instalaciones eléctricas, plomería, aire acondicionado y reparaciones generales en sucursal.',
            ],
            [
                'nombre'      => 'Seguridad',
                'descripcion' => 'Incidentes de seguridad física, accesos no autorizados, robos, vandalismo, alarmas y control de vigilancia.',
            ],
            [
                'nombre'      => 'Tecnologías de la Información',
                'descripcion' => 'Fallos de sistemas, red, POS, impresoras, software interno, correos y equipos de cómputo.',
            ],
            [
                'nombre'      => 'Operaciones',
                'descripcion' => 'Problemas del día a día en sucursal: flujos de trabajo, turnos, coordinación entre áreas y continuidad del servicio.',
            ],
            [
                'nombre'      => 'Limpieza y Servicios Generales',
                'descripcion' => 'Aseo, higiene, desechos, áreas comunes, baños y condiciones de limpieza del local.',
            ],
            [
                'nombre'      => 'Atención al Cliente',
                'descripcion' => 'Quejas, reclamos, atención deficiente, tiempos de espera y experiencia del cliente en sucursal.',
            ],
            [
                'nombre'      => 'Inventario y Logística',
                'descripcion' => 'Faltantes, daños de mercadería, recepción de pedidos, almacenamiento y movimiento de insumos.',
            ],
            [
                'nombre'      => 'Recursos Humanos',
                'descripcion' => 'Conflictos laborales, ausentismo, clima organizacional, capacitaciones y temas de personal.',
            ],
            [
                'nombre'      => 'Finanzas y Caja',
                'descripcion' => 'Diferencias de caja, pagos, facturación, arqueos y reportes económicos de sucursal.',
            ],
            [
                'nombre'      => 'Calidad y Cumplimiento',
                'descripcion' => 'Incumplimiento de normas internas, auditorías, protocolos de calidad y estándares corporativos.',
            ],
        ];

        foreach ($departamentos as $d) {
            $existe = DB::table('departamentos')->where('nombre', $d['nombre'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('departamentos')->insert([
                'nombre'      => $d['nombre'],
                'descripcion' => $d['descripcion'],
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        $nombres = [
            'Mantenimiento',
            'Seguridad',
            'Tecnologías de la Información',
            'Operaciones',
            'Limpieza y Servicios Generales',
            'Atención al Cliente',
            'Inventario y Logística',
            'Recursos Humanos',
            'Finanzas y Caja',
            'Calidad y Cumplimiento',
        ];
        DB::table('departamentos')->whereIn('nombre', $nombres)->delete();
    }
};
