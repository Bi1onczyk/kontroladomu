// Kopiowanie tekstu (klucz 2FA, kody awaryjne) do schowka. Tekst jest w atrybucie data-copy-text.
export default () => ({
    copied: false,
    supported: false,

    init() {
        this.supported = true;
    },

    async copy() {
        const text = this.$root.dataset.copyText ?? '';

        try {
            await navigator.clipboard.writeText(text);
        } catch (error) {
            this.fallbackCopy(text);
        }

        this.copied = true;
        setTimeout(() => {
            this.copied = false;
        }, 4000);
    },

    // Starsze przeglądarki mobilne bez navigator.clipboard.
    fallbackCopy(text) {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.className = 'fixed left-0 top-0 opacity-0';
        document.body.appendChild(area);
        area.select();

        try {
            document.execCommand('copy');
        } catch (error) {
            // Brak możliwości kopiowania: użytkownik zaznaczy tekst ręcznie.
        }

        document.body.removeChild(area);
    },

    get message() {
        return this.copied ? this.$root.dataset.copiedText : '';
    },
});
