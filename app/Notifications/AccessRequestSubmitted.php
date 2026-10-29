<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestSubmitted extends Notification
{
    public function __construct(
        public readonly string $organizationName,
        public readonly string $siteName,
        public readonly string $requesterName,
        public readonly string $requestedPeriod,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Nueva solicitud de acceso pendiente')
            ->greeting('Hola '.$notifiable->name)
            ->line("Hay una nueva solicitud pendiente para el sitio {$this->siteName} de {$this->organizationName}.")
            ->line("Solicitada por: {$this->requesterName}.")
            ->line("Periodo solicitado: {$this->requestedPeriod}.")
            ->action('Ingresar a AccesoSitios', route('login'))
            ->line('Ingresa a la aplicación para revisar los detalles y decidir esta solicitud.')
            ->salutation('Equipo de AccesoSitios');
    }
}
