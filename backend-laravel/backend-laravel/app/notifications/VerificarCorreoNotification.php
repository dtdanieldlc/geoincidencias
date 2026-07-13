<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificarCorreoNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $codigo,
        private readonly string $nombreUsuario
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma tu correo — GeoIncidencias')
            ->greeting("¡Hola, {$this->nombreUsuario}!")
            ->line('Gracias por registrarte en **GeoIncidencias**.')
            ->line('Usa el siguiente código para verificar tu correo electrónico:')
            ->line('')
            ->line('## ' . $this->codigo)
            ->line('')
            ->line('Este código es válido por **15 minutos**.')
            ->line('Si no creaste esta cuenta, puedes ignorar este correo.')
            ->salutation('— El equipo de GeoIncidencias');
    }
}
