<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wspólna baza maili kont: kolejka (wysyłka z crona, ADR-003), kanał mail, powitanie i stopka.
 *
 * Maile idą przez kolejkę, żeby czas odpowiedzi serwera nie zdradzał, czy konto istnieje.
 */
abstract class AccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Kanały wysyłki.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Szkielet wiadomości: powitanie "Dzień dobry," (bez imienia) i podpis serwisu.
     */
    protected function baseMessage(string $subjectKey): MailMessage
    {
        return (new MailMessage)
            ->subject(__($subjectKey))
            ->greeting(__('mail.greeting'))
            ->salutation(__('mail.signature'));
    }

    /**
     * Dopisuje stopkę: administrator danych, adres kontaktowy, informacja o automatycznej wiadomości.
     */
    protected function withFooter(MailMessage $message): MailMessage
    {
        return $message
            ->line(__('mail.footer_admin'))
            ->line(__('mail.footer_questions', ['email' => config('app.contact_email')]))
            ->line(__('mail.footer_automatic'));
    }
}
