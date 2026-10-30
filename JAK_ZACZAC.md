# Jak zacząć — kontroladomu.pl

Projekt jest już przygotowany lokalnie w `C:\xampp\htdocs\kontroladomu`. Główna kopia
dokumentacji znajduje się w `docs/`; oryginalny katalog `instrukcje/` jest ignorowany przez Git.

## Uruchomienie aplikacji

Na tym komputerze PHP 8.3 i MariaDB 10.11 są zainstalowane obok XAMPP, bez zmieniania jego
konfiguracji. Dokładne polecenia, konfigurację bazy i testy opisuje
[dokumentacja techniczna](docs/DOKUMENTACJA_TECHNICZNA.md).

W skrócie:

1. Otwórz folder projektu w VS Code.
2. W terminalu PowerShell uruchom serwer PHP 8.3 z `artisan serve`.
3. W drugim terminalu uruchom `npm run dev`, a następnie otwórz adres lokalny podany przez
   Artisan.
4. Testy, formatowanie i analizę statyczną uruchamiaj PHP 8.3, nie PHP z XAMPP.

## Instrukcje projektu

- `CLAUDE.md` — zasady i kontekst projektu.
- `.claude/agents/` — role pomocnicze przygotowane w oryginalnej paczce instrukcji.
- `docs/SPECYFIKACJA.md` — wymagania produktu i ustalenia właściciela.
- `docs/PLAN_ETAPOW.md` — kolejność etapów.
- `docs/zadania/` — zadania i stan prac.
- `docs/DECYZJE.md` — uzasadnienia decyzji technicznych.
- `docs/DOKUMENTACJA_TECHNICZNA.md` — uruchomienie, architektura i wdrożenie.

## Zmiana wymagań

Najpierw zaktualizuj właściwy punkt w `docs/SPECYFIKACJA.md`, a następnie opisz zmianę
w zadaniu projektu. Ustalenia wpływające na architekturę dokumentuj w `docs/DECYZJE.md`.
