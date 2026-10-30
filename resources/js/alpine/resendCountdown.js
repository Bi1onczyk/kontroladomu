// Odliczanie do ponownego wysłania (60 s). Licznik nie jest odczytywany co sekundę przez czytniki ekranu
// (aria-live="off"); po upływie czasu pojawia się jedno ogłoszenie "Możesz wysłać link ponownie".
export default () => ({
    seconds: 0,
    timer: null,

    init() {
        this.seconds = parseInt(this.$root.dataset.seconds ?? '60', 10);
        this.timer = setInterval(() => {
            this.seconds -= 1;
            if (this.seconds <= 0) {
                clearInterval(this.timer);
            }
        }, 1000);
    },

    get waiting() {
        return this.seconds > 0;
    },

    get ready() {
        return this.seconds <= 0;
    },

    get text() {
        return (this.$root.dataset.template ?? '').replace(':seconds', String(this.seconds));
    },

    get announcement() {
        return this.ready ? this.$root.dataset.readyText : '';
    },
});
