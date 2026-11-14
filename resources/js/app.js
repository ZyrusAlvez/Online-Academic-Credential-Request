// <x-select-menu>: a dropdown whose list scrolls within the page and can be searched (REQ-15-16).
// `options` is [{ value, label }]; `value` is bound to the Livewire property through x-modelable.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('selectMenu', (options) => ({
        options,
        value: '',
        open: false,
        query: '',
        active: 0,

        get chosen() {
            return this.options.find((option) => option.value === String(this.value ?? '')) ?? null;
        },

        get matches() {
            const query = this.query.trim().toLowerCase();

            return query === '' ? this.options : this.options.filter((option) => option.label.toLowerCase().includes(query));
        },

        show() {
            this.open = true;
            this.query = '';
            this.active = Math.max(0, this.options.findIndex((option) => option.value === this.chosen?.value));
            this.$nextTick(() => {
                this.$refs.search.focus();
                this.scrollToActive();
            });
        },

        close(focusButton = false) {
            this.open = false;

            if (focusButton) {
                this.$refs.button.focus();
            }
        },

        toggle() {
            this.open ? this.close(true) : this.show();
        },

        move(step) {
            if (this.matches.length === 0) {
                return;
            }

            this.active = (this.active + step + this.matches.length) % this.matches.length;
            this.$nextTick(() => this.scrollToActive());
        },

        choose(option) {
            if (!option) {
                return;
            }

            this.value = option.value;
            this.close(true);
        },

        scrollToActive() {
            this.$refs.list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' });
        },
    }));
});

// `wire:confirm` without the browser's confirm box (REQ-09-13).
//
// Livewire's own directive stores `el.__livewire_confirm(action, cancel)` and calls it on click.
// This hook runs after it and swaps in a version that opens <x-confirm-dialog> instead, so the
// markup keeps using plain `wire:confirm`. Pages without the dialog (or a future Livewire that
// renames the internal) keep the native confirm, so a confirmation is never skipped.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm' || directive.modifiers.includes('prompt')) {
            return;
        }

        if (typeof el.__livewire_confirm !== 'function') {
            return;
        }

        const native = el.__livewire_confirm;

        el.__livewire_confirm = (action, cancel) => {
            if (!document.querySelector('[data-confirm-dialog]')) {
                return native(action, cancel);
            }

            window.dispatchEvent(new CustomEvent('confirm-action', {
                detail: {
                    title: el.dataset.confirmTitle || 'Please confirm',
                    message: directive.expression.replaceAll('\\n', '\n') || 'Are you sure?',
                    label: el.dataset.confirmLabel || 'Confirm',
                    danger: el.dataset.confirmVariant === 'danger',
                    action,
                },
            }));
        };
    });
});
