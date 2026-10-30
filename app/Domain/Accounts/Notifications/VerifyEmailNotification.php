<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * E-mail z linkiem potwierdzającym adres. Link jest podpisany i ważny 24 godziny
 * (`auth.verification.expire`); nie loguje użytkownika (ADR-006).
 */
class VerifyEmailNotification extends AccountNotification
{
    /**
     * Treść wiadomości.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $message = $this->baseMessage('mail.verify_subject')
            ->line(__('mail.verify_intro'))
            ->action(__('mail.verify_button'), $url)
            ->line(__('mail.link_fallback'))
            ->line($url)
            ->line(__('mail.verify_ignore'));

        return $this->withFooter($message);
    }
}
