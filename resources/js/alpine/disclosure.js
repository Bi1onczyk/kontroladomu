// Rozwijany panel z potwierdzeniem (np. "Wygeneruj nowe kody" z ostrzeżeniem i przyciskiem "Anuluj").
export default () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    },

    close() {
        this.open = false;
    },

    get expanded() {
        return this.open ? 'true' : 'false';
    },
});
