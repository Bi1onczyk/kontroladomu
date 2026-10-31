// Cennik usług: przy zaznaczonym przeglądzie pokazuje blok z typami nieruchomości, a w wierszu blokuje pole ceny,
// gdy zaznaczono "Wycena indywidualna" albo odznaczono typ. Bez JS wszystkie pola są aktywne i widoczne,
// a serwer ignoruje wiersze bez zaznaczenia "Wykonuję".
export default () => ({
    init() {
        this.$root.querySelectorAll('[data-inspection]').forEach((block) => this.syncBlock(block));
        this.$root.querySelectorAll('[data-row]').forEach((row) => this.syncRow(row));
    },

    onChange(event) {
        const target = event.target;
        const toggle = target.closest('[data-inspection-toggle]');

        if (toggle) {
            const block = this.$root.querySelector(`[data-inspection="${toggle.dataset.inspectionToggle}"]`);

            if (block) {
                // Odznaczenie przeglądu odznacza wszystkie jego typy, żeby nic się nie zapisało po cichu.
                if (!toggle.checked) {
                    block.querySelectorAll('[data-enabled]').forEach((box) => {
                        box.checked = false;
                    });
                }

                block.hidden = !toggle.checked;
                block.querySelectorAll('[data-row]').forEach((row) => this.syncRow(row));
            }

            return;
        }

        const row = target.closest('[data-row]');

        if (row) {
            this.syncRow(row);
        }
    },

    selectAll(event) {
        const block = event.currentTarget.closest('[data-inspection]');

        block.querySelectorAll('[data-enabled]').forEach((box) => {
            box.checked = true;
        });
        block.querySelectorAll('[data-row]').forEach((row) => this.syncRow(row));
    },

    syncBlock(block) {
        const toggle = this.$root.querySelector(`[data-inspection-toggle="${block.dataset.inspection}"]`);
        const any = Array.from(block.querySelectorAll('[data-enabled]')).some((box) => box.checked);

        if (toggle) {
            toggle.checked = any;
        }

        block.hidden = !any;
    },

    syncRow(row) {
        const enabled = row.querySelector('[data-enabled]');
        const individual = row.querySelector('[data-individual]');
        const price = row.querySelector('[data-price]');
        const note = row.querySelector('[data-individual-note]');
        const active = enabled.checked;

        individual.disabled = !active;
        price.disabled = !active || individual.checked;
        price.setAttribute('aria-disabled', price.disabled ? 'true' : 'false');

        if (note) {
            note.hidden = !(active && individual.checked);
        }
    },
});
