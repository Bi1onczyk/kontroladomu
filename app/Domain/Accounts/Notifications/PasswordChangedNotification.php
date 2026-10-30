<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Powiadomienie o zmianie hasła. Bez adresu IP i nazwy urządzenia (minimalizacja danych).
 */
class PasswordChangedNotification extends AccountNotification
{
    /**
     * Treść wiadomości.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Data i godzina w strefie Europe/Warsaw (CLAUDE.md §7.9).
        $when = now()->timezone('Europe/Warsaw');

        $message = $this->baseMessage('mail.password_changed_subject')
            ->line(__('mail.password_changed_intro', [
                'date' => $when->format('d.m.Y'),
                'time' => $when->format('H:i'),
            ]))
            ->line(__('mail.password_changed_ok'))
            ->line(__('mail.password_changed_alert', ['email' => config('app.contact_email')]))
            ->action(__('mail.password_changed_button'), route('password.request'));

        return $this->withFooter($message);
    }
}
