<?php

namespace App\Core\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $clinicName
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Restablecer acceso a {$this->clinicName}")
            ->greeting('Solicitud de restablecimiento de contraseña')
            ->line("Recibimos una solicitud para cambiar la contraseña de tu cuenta en {$this->clinicName}.")
            ->action('Crear nueva contraseña', $this->resetUrl)
            ->line('Este enlace caduca en 60 minutos y solo puede utilizarse una vez.')
            ->line('Si no realizaste esta solicitud, puedes ignorar este mensaje.');
    }
}
