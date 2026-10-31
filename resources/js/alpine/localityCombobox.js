// Pole wyboru miejscowości (wzorzec WAI-ARIA 1.2 combobox) z podpowiedziami z endpointu /miejscowosci/szukaj.
// Konfiguracja przychodzi z atrybutów data-* (adres, teksty, wartość zapisana). Teksty trafiają do strony WYŁĄCZNIE
// przez x-text (nigdy HTML), więc nazwa miejscowości z odpowiedzi nie może wstrzyknąć kodu (audyt 003).
const MIN_CHARS = 2;
const DEBOUNCE_MS = 250;
const LIMIT = 10;

export default () => ({
    query: '',
    simc: '',
    results: [],
    activeIndex: -1,
    open: false,
    editing: true,
    canToggle: false,
    status: '',
    messages: {},
    url: '',
    timer: null,
    controller: null,

    init() {
        const data = this.$root.dataset;

        this.url = data.url ?? '';
        this.messages = JSON.parse(data.messages ?? '{}');
        this.simc = data.simc ?? '';
        this.query = data.label ?? '';
        // Zapisana miejscowość: pole ukryte do czasu kliknięcia "Zmień miejscowość" (chyba że jest błąd walidacji).
        const saved = this.simc !== '' && data.hasError !== '1';
        this.canToggle = saved;
        this.editing = !saved;
    },

    // ---------------------------------------------------------------- zmiana trybu

    startEditing() {
        this.editing = true;
        this.$nextTick(() => this.$refs.input.focus());
    },

    get showChange() {
        return this.canToggle && !this.editing;
    },

    // ---------------------------------------------------------------- wpisywanie i szukanie

    onInput() {
        // Każda zmiana tekstu unieważnia wybór: nie da się zapisać tekstu, którego nie wybrano z listy.
        this.simc = '';
        this.scheduleSearch();
    },

    onPartsChange() {
        if (this.query.trim().length >= MIN_CHARS) {
            this.scheduleSearch();
        }
    },

    scheduleSearch() {
        clearTimeout(this.timer);
        this.abort();

        if (this.query.trim().length < MIN_CHARS) {
            this.results = [];
            this.open = false;
            this.activeIndex = -1;
            this.status = '';

            return;
        }

        this.timer = setTimeout(() => this.search(), DEBOUNCE_MS);
    },

    abort() {
        if (this.controller) {
            this.controller.abort();
            this.controller = null;
        }
    },

    async search() {
        this.abort();
        const controller = new AbortController();
        this.controller = controller;

        const params = new URLSearchParams({
            q: this.query.trim(),
            limit: String(LIMIT),
            // Zawsze "1" albo "0" (nie "true"/"false").
            include_parts: this.$refs.parts && this.$refs.parts.checked ? '1' : '0',
        });

        this.status = this.messages.searching ?? '';

        try {
            const response = await fetch(`${this.url}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (response.status === 429) {
                this.fail(this.messages.rate_limited);

                return;
            }

            if (response.status === 422) {
                this.fail(this.messages.invalid);

                return;
            }

            if (!response.ok) {
                this.fail(this.messages.error);

                return;
            }

            const body = await response.json();
            this.showResults(Array.isArray(body.data) ? body.data : []);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            this.fail(this.messages.error);
        }
    },

    fail(message) {
        this.results = [];
        this.open = false;
        this.activeIndex = -1;
        this.status = message ?? '';
    },

    showResults(list) {
        this.results = list;
        this.activeIndex = -1;
        this.open = list.length > 0;
        this.status = list.length > 0
            ? this.fill(this.messages.found, { count: list.length })
            : this.messages.none ?? '';
    },

    // ---------------------------------------------------------------- lista i klawiatura

    // Lista do wyświetlenia: gotowe teksty i atrybuty, żeby szablon nie musiał niczego liczyć.
    get items() {
        return this.results.map((item, index) => ({
            simc: item.simc,
            index,
            name: item.name,
            kind: item.kind_label ?? '',
            detail: this.fill(this.messages.detail, {
                municipality: item.municipality,
                county: item.county,
                voivodeship: item.voivodeship,
            }),
            optionId: `locality-option-${index}`,
            selected: index === this.activeIndex ? 'true' : 'false',
            cls: index === this.activeIndex ? 'bg-brand-100' : 'bg-paper',
        }));
    },

    get expanded() {
        return this.open ? 'true' : 'false';
    },

    get activeId() {
        return this.open && this.activeIndex >= 0 ? `locality-option-${this.activeIndex}` : null;
    },

    next(event) {
        if (this.results.length === 0) {
            return;
        }

        event.preventDefault();
        this.open = true;
        this.activeIndex = (this.activeIndex + 1) % this.results.length;
    },

    previous(event) {
        if (this.results.length === 0) {
            return;
        }

        event.preventDefault();
        this.open = true;
        this.activeIndex = this.activeIndex <= 0 ? this.results.length - 1 : this.activeIndex - 1;
    },

    onEnter(event) {
        // Enter wybiera pozycję tylko przy otwartej liście; inaczej formularz wysyła się normalnie.
        if (this.open && this.activeIndex >= 0) {
            event.preventDefault();
            this.select(this.results[this.activeIndex]);
        } else if (this.open) {
            event.preventDefault();
        }
    },

    close() {
        this.open = false;
        this.activeIndex = -1;
    },

    // ---------------------------------------------------------------- wybór

    choose(event) {
        const index = Number(event.currentTarget.dataset.index);

        if (this.results[index]) {
            this.select(this.results[index]);
        }
    },

    select(item) {
        this.simc = item.simc;
        this.query = item.label;
        this.status = this.fill(this.messages.selected, { label: item.label });
        this.close();
        this.$refs.input.focus();
    },

    fill(template, values) {
        let text = template ?? '';

        Object.keys(values).forEach((key) => {
            text = text.split(`:${key}`).join(String(values[key] ?? ''));
        });

        return text;
    },
});
