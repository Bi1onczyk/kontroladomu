// Komunikat, który można zamknąć (flash sukcesu). Komunikaty błędów nie znikają same.
export default () => ({
    open: true,

    close() {
        this.open = false;
    },
});
