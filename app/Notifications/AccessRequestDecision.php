<?php

namespace App\Notifications;

use App\Enums\AccessRequestStatus;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestDecision extends Notification
{
    public function __construct(
        public readonly AccessRequestStatus $status,
        public readonly string $organizationName,
        public readonly string $siteName,
        public readonly string $reviewerName,
        public readonly string $requestedPeriod,
        public readonly ?string $comment,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = match ($this->status) {
            AccessRequestStatus::APPROVED => 'aprobada',
            AccessRequestStatus::REJECTED => 'rechazada',
            default => 'actualizada',
        };

        $mail = (new MailMessage())
            ->subject('Tu solicitud de acceso fue '.$statusLabel)
            ->greeting('Hola '.$notifiable->name)
            ->line("Tu solicitud para el sitio {$this->siteName} de {$this->organizationName} fue {$statusLabel}.")
            ->line("Periodo solicitado: {$this->requestedPeriod}.")
            ->line("Decidió: {$this->reviewerName}.");

        if ($this->comment !== null && $this->comment !== '') {
            $mail->line('Comentario: '.$this->comment);
        }

        return $mail
            ->action('Ingresar a AccesoSitios', route('login'))
            ->line('Ingresa a la aplicación para consultar la solicitud y sus detalles.')
            ->salutation('Equipo de AccesoSitios');
    }
}
