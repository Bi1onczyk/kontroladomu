<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail z linkiem do ustawienia nowego hasła (ważny `auth.passwords.users.expire` minut, jednorazowy).
 */
class ResetPasswordNotification extends AccountNotification
{
    /**
     * @param  string  $token  Token z brokera haseł.
     */
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * Treść wiadomości.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $message = $this->baseMessage('mail.reset_subject')
            ->line(__('mail.reset_intro'))
            ->action(__('mail.reset_button'), $url)
            ->line(__('mail.link_fallback'))
            ->line($url)
            ->line(__('mail.reset_ignore'));

        return $this->withFooter($message);
    }
}
