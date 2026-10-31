// Licznik znaków pod polem tekstowym. Komunikat dla czytnika ekranu pojawia się dopiero blisko limitu,
// żeby nie czytać każdego wpisanego znaku (data-max, data-template z ":count" i ":max").
export default () => ({
    count: 0,
    max: 0,
    template: '',

    init() {
        this.max = Number(this.$root.dataset.max ?? 0);
        this.template = this.$root.dataset.template ?? '';
        this.count = this.$refs.field.value.length;
    },

    update() {
        this.count = this.$refs.field.value.length;
    },

    get text() {
        return this.template.replace(':count', String(this.count)).replace(':max', String(this.max));
    },

    // Do czytnika: tylko w ostatnich 10% limitu.
    get live() {
        return this.count >= this.max * 0.9 ? this.text : '';
    },
});
