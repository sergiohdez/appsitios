<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordRecoveryLink extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresIn = config('auth.passwords.users.expire', 60);

        return (new MailMessage())
            ->subject('Restablece tu contraseña de AccesoSitios')
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de AccesoSitios.')
            ->action('Restablecer contraseña', route('password.reset', [
                'token' => $this->token,
                'purpose' => 'reset',
            ]))
            ->line("El enlace vence en {$expiresIn} minutos y solo puede utilizarse una vez.")
            ->line('Si no solicitaste este cambio, puedes ignorar este correo.')
            ->salutation('Equipo de AccesoSitios');
    }
}
