<?php

return [
    // --- wspólne
    'greeting' => 'Dzień dobry,',
    'signature' => 'kontroladomu.pl',
    'link_fallback' => 'Jeśli przycisk nie działa, skopiuj ten adres do przeglądarki:',
    'footer_admin' => 'Administratorem danych jest Dariusz Magdziak, ul. Modrzewiowa 6, 62-069 Dąbrówka.',
    'footer_questions' => 'Pytania: :email',
    'footer_automatic' => 'To wiadomość wygenerowana automatycznie.',

    // --- potwierdzenie adresu e-mail
    'verify_subject' => 'Potwierdź adres e-mail w kontroladomu.pl',
    'verify_intro' => 'Aby aktywować konto, potwierdź adres e-mail. Link działa 24 godziny.',
    'verify_button' => 'Potwierdź adres e-mail',
    'verify_ignore' => 'Jeśli konto nie zostało założone przez Ciebie, zignoruj tę wiadomość. Konto nie zostanie aktywowane.',

    // --- reset hasła
    'reset_subject' => 'Zmiana hasła w kontroladomu.pl',
    'reset_intro' => 'Dostaliśmy prośbę o zmianę hasła do konta. Link działa 60 minut i tylko raz.',
    'reset_button' => 'Ustaw nowe hasło',
    'reset_ignore' => 'Jeśli to nie Ty prosiłeś(-aś) o zmianę hasła, zignoruj tę wiadomość. Hasło pozostaje bez zmian.',

    // --- hasło zmienione (PasswordChanged)
    'password_changed_subject' => 'Hasło do konta zostało zmienione',
    'password_changed_intro' => 'Hasło do Twojego konta w kontroladomu.pl zostało zmienione dnia :date o godz. :time. Inne urządzenia zostały wylogowane.',
    'password_changed_ok' => 'Jeśli to Ty zmieniałeś(-aś) hasło, nic więcej nie trzeba robić.',
    'password_changed_alert' => 'Jeśli to nie Ty, od razu ustaw nowe hasło i napisz do nas: :email.',
    'password_changed_button' => 'Ustaw nowe hasło',

    // --- reset weryfikacji dwuetapowej (TwoFactorReset)
    'twofa_reset_subject' => 'Weryfikacja dwuetapowa została wyłączona w Twoim koncie',
    'twofa_reset_intro' => 'Administrator serwisu zresetował weryfikację dwuetapową Twojego konta dnia :date, na Twoją prośbę. Wylogowaliśmy Cię ze wszystkich urządzeń.',
    'twofa_reset_required' => 'Przy następnym logowaniu trzeba będzie ustawić kod z aplikacji od nowa.',
    'twofa_reset_optional' => 'Możesz włączyć ją ponownie w ustawieniach konta.',
    'twofa_reset_alert' => 'Jeśli o to nie prosiłeś(-aś), od razu zmień hasło i napisz do nas: :email.',
    'twofa_reset_button' => 'Zaloguj się',

    // --- próba rejestracji na istniejący adres (RegisterAttempt)
    'register_attempt_subject' => 'Ktoś próbował założyć konto na Twój adres',
    'register_attempt_text' => 'Na ten adres e-mail istnieje już konto w kontroladomu.pl. Jeśli to Ty, zaloguj się lub użyj opcji „Nie pamiętam hasła”. Jeśli nie, nic nie musisz robić.',
    'register_attempt_button' => 'Przejdź do logowania',
];
