<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Wiadomość dla posiadacza konta, gdy ktoś próbował założyć konto na jego adres.
 * Bez imienia i innych danych konta.
 */
class RegisterAttemptNotification extends AccountNotification
{
    /**
     * Treść wiadomości.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->baseMessage('mail.register_attempt_subject')
            ->line(__('mail.register_attempt_text'))
            ->action(__('mail.register_attempt_button'), route('login'));

        return $this->withFooter($message);
    }
}
