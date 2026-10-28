<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPasswordInvitation extends Notification
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
            ->subject('Enlace de acceso a AccesoSitios')
            ->greeting('Hola '.$notifiable->name)
            ->line('Se creó una cuenta de AccesoSitios para ti. Usa el enlace para definir tu contraseña.')
            ->action('Definir contraseña', route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'purpose' => 'activate',
            ]))
            ->line("El enlace vence en {$expiresIn} minutos y solo puede utilizarse una vez.")
            ->salutation('Equipo de AccesoSitios');
    }
}
