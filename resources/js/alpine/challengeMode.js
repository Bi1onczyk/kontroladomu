// Ekran kodu 2FA: przełączanie między kodem z aplikacji a kodem awaryjnym.
// Bez JS widać oba pola (serwer przyjmie którekolwiek). Tryb początkowy: data-initial="app|recovery".
export default () => ({
    mode: 'app',

    init() {
        this.mode = this.$root.dataset.initial === 'recovery' ? 'recovery' : 'app';
    },

    get isApp() {
        return this.mode === 'app';
    },

    get isRecovery() {
        return this.mode === 'recovery';
    },

    showRecovery() {
        this.mode = 'recovery';
        this.$nextTick(() => this.$refs.recovery.focus());
    },

    showApp() {
        this.mode = 'app';
        this.$nextTick(() => this.$refs.code.focus());
    },
});
