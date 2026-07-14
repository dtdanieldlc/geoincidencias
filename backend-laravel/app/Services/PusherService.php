<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de Pusher Channels hecho a mano con el cliente HTTP de
 * Laravel, para no depender del paquete de Composer "pusher/pusher-php-server"
 * (evita tener que correr `composer require` / regenerar composer.lock).
 *
 * Implementa exactamente lo que necesita el chat:
 *  - trigger():     dispara un evento a uno o más canales.
 *  - authChannel(): firma la autenticación de un canal privado para el
 *                    cliente JS (pusher-js).
 *
 * Protocolo oficial: https://pusher.com/docs/channels/library_auth_reference/rest-api/
 */
class PusherService
{
    protected string $appId;
    protected string $key;
    protected string $secret;
    protected string $cluster;

    public function __construct()
    {
        $this->appId   = (string) config('services.pusher.app_id');
        $this->key     = (string) config('services.pusher.key');
        $this->secret  = (string) config('services.pusher.secret');
        $this->cluster = (string) config('services.pusher.cluster');
    }

    public function configurado(): bool
    {
        return $this->appId && $this->key && $this->secret && $this->cluster;
    }

    /**
     * Dispara un evento a uno o varios canales.
     * $channels puede ser un string o un array de strings.
     */
    public function trigger(array|string $channels, string $event, array $data): bool
    {
        if (! $this->configurado()) return false;

        $channels = is_array($channels) ? $channels : [$channels];

        $body = json_encode([
            'name'     => $event,
            'channels' => $channels,
            'data'     => json_encode($data),
        ]);

        $path = "/apps/{$this->appId}/events";
        $qs   = $this->firmarPeticion('POST', $path, $body);

        $resp = Http::withBody($body, 'application/json')
            ->post("https://api-{$this->cluster}.pusher.com{$path}?{$qs}");

        return $resp->successful();
    }

    /**
     * Genera la respuesta de autenticación que espera pusher-js para
     * poder suscribirse a un canal privado ("private-...").
     */
    public function authChannel(string $socketId, string $channelName): array
    {
        $stringToSign = "{$socketId}:{$channelName}";
        $signature    = hash_hmac('sha256', $stringToSign, $this->secret);

        return ['auth' => "{$this->key}:{$signature}"];
    }

    protected function firmarPeticion(string $metodo, string $path, string $body): string
    {
        $params = [
            'auth_key'       => $this->key,
            'auth_timestamp' => time(),
            'auth_version'   => '1.0',
            'body_md5'       => md5($body),
        ];
        ksort($params);
        $qs = http_build_query($params);

        $stringToSign = "{$metodo}\n{$path}\n{$qs}";
        $firma        = hash_hmac('sha256', $stringToSign, $this->secret);

        return $qs . '&auth_signature=' . $firma;
    }
}
