<?php

namespace App\Domain\Accounts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Wiadomość z linkiem potwierdzającym NOWY adres e-mail (zmiana adresu przed potwierdzeniem konta).
 * Wysyłana na adres docelowy (powiadomienie "na żądanie"); link jest podpisany, ważny 24 godziny
 * i nie loguje. Adres konta zmienia się dopiero po kliknięciu.
 */
class VerifyPendingEmailNotification extends AccountNotification
{
    /**
     * @param  int  $userId  Konto, które zmienia adres.
     * @param  string  $email  Nowy adres (docelowy).
     */
    public function __construct(private readonly int $userId, private readonly string $email) {}

    /**
     * Treść wiadomości (te same teksty co przy zwykłym potwierdzeniu adresu).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'email.change.verify',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            ['id' => $this->userId, 'hash' => sha1($this->email)],
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
