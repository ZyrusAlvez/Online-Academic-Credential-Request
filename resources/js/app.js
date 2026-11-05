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
