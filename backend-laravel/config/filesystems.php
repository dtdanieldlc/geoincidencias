<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE (Railway): el disco 'local'/'public' vive en el filesystem
    | del contenedor. Railway lo recrea desde cero en cada deploy/restart,
    | así que cualquier foto subida por ese medio se PIERDE. Dos opciones:
    |
    |  A) Rápida, sin cuentas nuevas: crear un "Volume" en Railway y montarlo
    |     en /storage/app/public dentro del servicio del backend. Con eso
    |     'public' ya queda persistente sin tocar nada más aquí.
    |
    |  B) Recomendada a mediano plazo: usar un storage tipo S3 (Cloudflare R2
    |     tiene capa gratuita generosa y es compatible con la API de S3).
    |     Crea el bucket, consigue las credenciales, y en Railway define:
    |       FILESYSTEM_DISK=s3
    |       AWS_ACCESS_KEY_ID=...
    |       AWS_SECRET_ACCESS_KEY=...
    |       AWS_DEFAULT_REGION=auto
    |       AWS_BUCKET=nombre-del-bucket
    |       AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
    |       AWS_USE_PATH_STYLE_ENDPOINT=true
    |     y listo, sin cambiar código: el disco 's3' de abajo ya está armado
    |     para leer esas variables. Necesitas el paquete
    |     `composer require league/flysystem-aws-s3-v3` si no está instalado.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'visibility'              => 'public',
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
