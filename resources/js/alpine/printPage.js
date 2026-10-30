// Przycisk "Drukuj" (kody awaryjne). Zdarzenie onclick="" jest zabronione przez CSP, więc robi to Alpine.
export default () => ({
    supported: false,

    init() {
        this.supported = typeof window.print === 'function';
    },

    print() {
        window.print();
    },
});
