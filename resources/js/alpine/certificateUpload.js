// Formularz dodawania zaświadczenia: blokada daty przy "Bezterminowo" i podgląd nazwy wybranego pliku.
export default () => ({
    noExpiry: false,
    fileInfo: '',

    init() {
        this.noExpiry = this.$refs.noExpiry.checked;
    },

    get dateDisabled() {
        return this.noExpiry;
    },

    onNoExpiry() {
        this.noExpiry = this.$refs.noExpiry.checked;
    },

    onFile() {
        const file = this.$refs.file.files[0];

        if (!file) {
            this.fileInfo = '';

            return;
        }

        const size = file.size >= 1048576
            ? `${(file.size / 1048576).toFixed(1).replace('.', ',')} MB`
            : `${Math.max(1, Math.round(file.size / 1024))} KB`;

        this.fileInfo = (this.$root.dataset.chosen ?? '').replace(':name', file.name).replace(':size', size);
    },
});
