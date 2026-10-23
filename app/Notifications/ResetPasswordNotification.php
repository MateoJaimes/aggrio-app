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
        private ?string $url = null,
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
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $this->resetUrl($notifiable),
                'minutes' => $minutes,
            ]);
    }

    private function resetUrl(object $notifiable): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base.'/auth/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
