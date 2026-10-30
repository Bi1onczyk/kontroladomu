<?php

// DO WERYFIKACJI PRZEZ PRAWNIKA: wszystkie teksty w tym pliku to szkice.
// Dane administratora pochodzą wyłącznie z CLAUDE.md §1. Miejsca oznaczone
// [do uzupełnienia] wymagają decyzji właściciela lub prawnika.
return [
    'draft_notice' => 'Szkic dokumentu. Treść zostanie zweryfikowana przed uruchomieniem serwisu.',
    'version' => 'Wersja z dnia :date',
    'administrator_name' => 'Dariusz Magdziak',
    'administrator_address' => 'ul. Modrzewiowa 6, 62-069 Dąbrówka',
    'administrator_nip' => 'NIP 6931171824',

    'terms' => [
        'title' => 'Regulamin',
        'description' => 'Zasady korzystania z serwisu kontroladomu.pl (szkic).',
        'sections' => [
            ['title' => 'Kto prowadzi serwis', 'text' => 'Serwis kontroladomu.pl prowadzi Dariusz Magdziak, ul. Modrzewiowa 6, 62-069 Dąbrówka, NIP 6931171824 (dalej: administrator). Kontakt: :email.'],
            ['title' => 'Czym jest serwis', 'text' => 'Serwis łączy właścicieli i zarządców nieruchomości ze specjalistami od okresowych przeglądów: kominiarskich, instalacji elektrycznej i piorunochronnej, ogólnobudowlanych, gazowych oraz ekologicznych (szamba i przydomowe oczyszczalnie ścieków). Serwis jest w budowie, a zakres funkcji będzie rozszerzany.'],
            ['title' => 'Rozliczenia', 'text' => 'Serwis nie pośredniczy w płatnościach za przeglądy. Rozliczenie następuje bezpośrednio między klientem a fachowcem.'],
            ['title' => 'Konta', 'text' => 'Konto można założyć jako właściciel lub zarządca nieruchomości albo jako fachowiec. Adres e-mail trzeba potwierdzić. Dla kont fachowców i administratorów obowiązkowa jest weryfikacja dwuetapowa. [do uzupełnienia: zasady usuwania i blokowania kont]'],
            ['title' => 'Weryfikacja fachowców', 'text' => 'Administrator sprawdza zaświadczenia i uprawnienia dodane przez fachowców. Oznaczenie weryfikacji dotyczy wyłącznie sprawdzonych dokumentów. [do uzupełnienia: zakres i skutki weryfikacji]'],
            ['title' => 'Opinie', 'text' => 'Opinię może wystawić tylko zalogowany klient po przeglądzie oznaczonym jako wykonany. Fachowiec może odpowiedzieć na opinię i ją zgłosić, ale nie może jej usunąć. Sposób weryfikowania opinii zostanie opisany na stronie serwisu.'],
            ['title' => 'Treści promowane', 'text' => 'Wyróżnienia płatne są oznaczone w wynikach wyszukiwania napisem „Promowane”.'],
            ['title' => 'Protokoły kominiarskie', 'text' => 'Protokół z okresowej kontroli przewodów kominowych sporządza się elektronicznie w systemie CEEB. Serwis nie zastępuje CEEB. Fachowiec wpisuje numer e-protokołu z CEEB i może dołączyć jego PDF. Dokument wygenerowany przez serwis ma charakter informacyjny.'],
            ['title' => 'Zgłaszanie treści', 'text' => 'Mechanizm zgłaszania nielegalnych treści jest w przygotowaniu. Do czasu jego uruchomienia zgłoszenia można wysyłać na adres :email.'],
            ['title' => 'Reklamacje, odpowiedzialność i zmiany regulaminu', 'text' => '[do uzupełnienia przez prawnika]'],
        ],
    ],

    'privacy' => [
        'title' => 'Polityka prywatności',
        'description' => 'Informacje o przetwarzaniu danych osobowych w serwisie kontroladomu.pl (szkic).',
        'sections' => [
            ['title' => 'Administrator danych', 'text' => 'Administratorem danych jest Dariusz Magdziak, ul. Modrzewiowa 6, 62-069 Dąbrówka, NIP 6931171824. Kontakt: :email.'],
            ['title' => 'Jakie dane i po co', 'items' => [
                'Imię i nazwisko, adres e-mail, hasło (przechowywane w postaci nieodwracalnego skrótu): prowadzenie konta (art. 6 ust. 1 lit. b RODO).',
                'Nazwa firmy i NIP (konta fachowców): prowadzenie konta fachowca (art. 6 ust. 1 lit. b RODO).',
                'Zgoda na przypomnienia i informacje o serwisie: wysyłka wiadomości e-mail (art. 6 ust. 1 lit. a RODO). Zgoda jest dobrowolna i można ją cofnąć w każdej chwili.',
                'Adres IP, dziennik zdarzeń i sesje: bezpieczeństwo konta i serwisu (art. 6 ust. 1 lit. f RODO).',
                'Tajny klucz weryfikacji dwuetapowej i kody awaryjne (przechowywane w postaci zaszyfrowanej): zabezpieczenie konta (art. 6 ust. 1 lit. f RODO).',
            ]],
            ['title' => 'Odbiorcy danych', 'items' => [
                'Cloudflare: zabezpieczenie formularzy przed robotami (Turnstile) i ochrona serwisu. [do uzupełnienia: dane podmiotu, transfer poza EOG]',
                'Have I Been Pwned: sprawdzamy, czy hasło nie występuje w wyciekach. Wysyłamy tylko fragment skrótu hasła, nigdy samo hasło.',
                'Dostawca hostingu i poczty: [do uzupełnienia: nazwa podmiotu].',
            ]],
            ['title' => 'Jak długo przechowujemy dane', 'items' => [
                'Dane konta: do czasu usunięcia konta. [do uzupełnienia: okresy związane z roszczeniami]',
                'Konta z niepotwierdzonym adresem e-mail: do 7 dni. [propozycja, do potwierdzenia]',
                'Dziennik zdarzeń: do 90 dni. [propozycja; dla działań administratorów dłużej, do potwierdzenia]',
            ]],
            ['title' => 'Twoje prawa', 'text' => 'Masz prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia oraz sprzeciwu, a także prawo cofnięcia zgody i wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych. Eksport i usunięcie konta z poziomu panelu będą dostępne wkrótce. Do tego czasu napisz na adres :email.'],
        ],
    ],

    'cookies' => [
        'title' => 'Polityka cookies',
        'description' => 'Informacje o plikach cookies w serwisie kontroladomu.pl (szkic).',
        'sections' => [
            ['title' => 'Cookies niezbędne', 'text' => 'Serwis używa wyłącznie plików cookies niezbędnych do działania: utrzymania sesji po zalogowaniu i ochrony formularzy przed fałszowaniem żądań. Nie wymagają zgody.'],
            ['title' => 'Zabezpieczenie przed robotami', 'text' => 'Na wybranych formularzach (rejestracja, reset hasła, a po kilku nieudanych próbach także logowanie) działa narzędzie Cloudflare Turnstile. Może ono zapisywać własne dane techniczne w przeglądarce.'],
            ['title' => 'Analityka i marketing', 'text' => 'Serwis nie używa obecnie analityki ani reklam. Gdy się pojawią, przed ich uruchomieniem zostanie pokazany wybór zgód, a domyślnie pozostaną włączone tylko cookies niezbędne.'],
            ['title' => 'Usuwanie cookies', 'text' => 'Cookies można usunąć w ustawieniach przeglądarki. Po usunięciu cookies sesji trzeba zalogować się ponownie.'],
        ],
    ],

    'contact' => [
        'title' => 'Kontakt',
        'description' => 'Dane kontaktowe administratora serwisu kontroladomu.pl (szkic).',
        'admin_title' => 'Administrator serwisu',
        'email_label' => 'Adres e-mail',
        'form_note' => 'Formularz kontaktowy jest w przygotowaniu. Na razie prosimy o wiadomość na adres e-mail.',
        'report_note' => 'Zgłoszenia nielegalnych treści: mechanizm w przygotowaniu. Do tego czasu prosimy o wiadomość na ten sam adres.',
    ],
];
