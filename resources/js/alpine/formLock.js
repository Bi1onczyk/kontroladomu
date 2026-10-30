// Blokada podwójnego wysłania formularza. Po kliknięciu przycisk zmienia podpis na wersję "...trwa"
// i ignoruje kolejne kliknięcia (aria-disabled zamiast disabled, żeby przycisk nie znikał czytnikom ekranu).
export default () => ({
    busy: false,

    init() {
        // Powrót strzałką "Wstecz" przywraca stronę z pamięci podręcznej przeglądarki: odblokuj przycisk.
        window.addEventListener('pageshow', () => {
            this.busy = false;
        });
    },

    onSubmit(event) {
        if (this.busy) {
            event.preventDefault();

            return;
        }

        this.busy = true;
    },

    get idle() {
        return !this.busy;
    },

    get ariaDisabled() {
        return this.busy ? 'true' : 'false';
    },
});
