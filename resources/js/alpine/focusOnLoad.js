// Przenosi fokus na element po załadowaniu strony (nagłówek wyniku, podsumowanie błędów).
// Element musi mieć tabindex="-1".
export default () => ({
    init() {
        this.$nextTick(() => {
            this.$el.focus();
        });
    },
});
