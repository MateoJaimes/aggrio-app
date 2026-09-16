<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de "olvidé mi contraseña" (RF005).
 *
 * La notificación por defecto de Laravel apunta a una ruta web `password.reset`
 * que este proyecto no tiene: el formulario vive en el frontend Angular, así que
 * el enlace se arma contra FRONTEND_URL.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        public string $token,
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
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contraseña de '.config('app.name'))
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $this->resetUrl($notifiable))
            ->line("Este enlace caduca en {$minutes} minutos.")
            ->line('Si no solicitaste el cambio, puedes ignorar este correo: tu contraseña no se modificará.')
            ->salutation('El equipo de '.config('app.name'));
    }

    private function resetUrl(object $notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base.'/auth/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
