<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Powiadomienie o zresetowaniu 2FA przez administratora (na prośbę użytkownika).
 */
class TwoFactorResetNotification extends AccountNotification
{
    /**
     * @param  bool  $twoFactorRequired  Czy konto ma rolę wymagającą 2FA (inny tekst zakończenia).
     */
    public function __construct(private readonly bool $twoFactorRequired) {}

    /**
     * Treść wiadomości.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $when = now()->timezone('Europe/Warsaw');

        $message = $this->baseMessage('mail.twofa_reset_subject')
            ->line(__('mail.twofa_reset_intro', ['date' => $when->format('d.m.Y')]))
            ->line(__($this->twoFactorRequired ? 'mail.twofa_reset_required' : 'mail.twofa_reset_optional'))
            ->line(__('mail.twofa_reset_alert', ['email' => config('app.contact_email')]))
            ->action(__('mail.twofa_reset_button'), route('login'));

        return $this->withFooter($message);
    }
}
