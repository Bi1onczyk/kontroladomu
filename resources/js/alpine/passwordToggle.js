// Pole hasła z przyciskiem "Pokaż" / "Ukryj". Etykiety przychodzą z atrybutów data-* (teksty z lang/pl).
export default () => ({
    shown: false,
    ready: false,

    init() {
        // Bez JS przycisk jest ukryty (nic by nie robił), z JS pojawia się po starcie Alpine.
        this.ready = true;
    },

    toggle() {
        this.shown = !this.shown;
        this.$refs.input.type = this.shown ? 'text' : 'password';
    },

    get label() {
        const { showLabel, hideLabel } = this.$root.dataset;

        return this.shown ? hideLabel : showLabel;
    },

    get pressed() {
        return this.shown ? 'true' : 'false';
    },
});
