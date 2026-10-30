// Gdy skrypt Cloudflare Turnstile nie załaduje się w ciągu 10 s (blokada skryptów, brak zasięgu),
// pokazujemy komunikat. Przycisk wysyłania zostaje aktywny: serwer i tak odrzuci żądanie bez tokenu.
export default () => ({
    failed: false,

    init() {
        setTimeout(() => {
            if (typeof window.turnstile === 'undefined') {
                this.failed = true;
            }
        }, 10000);
    },
});
