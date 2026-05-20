<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public function __construct(
        public string $token,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = URL::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Restablecer contraseña'))
            ->line(__('Recibiste este correo porque se solicitó restablecer la contraseña de tu cuenta.'))
            ->action(__('Restablecer contraseña'), url($resetUrl))
            ->line(Lang::get('Este enlace para restablecer tu contraseña expirará en :count minutos.', [
                'count' => $expireMinutes,
            ]))
            ->line(__('Si no solicitaste este cambio, no necesitas realizar ninguna acción.'))
            ->salutation(__('Saludos, Equipo de Pintech'));
    }
}
