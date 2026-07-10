<?php

namespace App\Mail;

use App\Models\Incidencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaIncidenciaCritica extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Incidencia $incidencia)
    {
    }

    public function build()
    {
        return $this->subject("🚨 Incidencia crítica: {$this->incidencia->titulo}")
            ->view('emails.alerta-critica')
            ->with(['incidencia' => $this->incidencia]);
    }
}
