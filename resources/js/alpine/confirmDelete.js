// Potwierdzenie usunięcia w treści strony (bez okna modalnego). Element <details> działa bez JS;
// skrypt dodaje przycisk "Anuluj" i przenosi fokus na niego po rozwinięciu (bezpieczniejsza opcja).
export default () => ({
    ready: false,

    init() {
        this.ready = true;
    },

    onToggle() {
        if (this.$root.open) {
            this.$nextTick(() => this.$refs.cancel.focus());
        }
    },

    cancel() {
        this.$root.open = false;
        this.$refs.summary.focus();
    },
});
