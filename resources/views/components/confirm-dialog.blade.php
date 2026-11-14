{{--
    Replaces the browser's confirm box for every `wire:confirm` (REQ-09-13). resources/js/app.js
    dispatches `confirm-action` with the message, the optional data-confirm-* texts and the action
    callback; the action runs only when the confirm button is pressed.
--}}
<div
    data-confirm-dialog
    x-data="{ title: '', message: '', label: '', danger: false, action: null,
        ask(detail) {
            this.title = detail.title; this.message = detail.message; this.label = detail.label;
            this.danger = detail.danger; this.action = detail.action;
            $flux.modal('confirm-action').show();
        },
        proceed() {
            const action = this.action;
            this.action = null;
            $flux.modal('confirm-action').close();
            action?.();
        } }"
    x-on:confirm-action.window="ask($event.detail)"
>
    <flux:modal name="confirm-action" class="w-full md:w-[28rem]" x-on:close="action = null">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="title"></flux:heading>
                <flux:text class="whitespace-pre-line" x-text="message"></flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" x-show="! danger" x-on:click="proceed()"><span x-text="label"></span></flux:button>
                <flux:button variant="danger" x-show="danger" x-on:click="proceed()"><span x-text="label"></span></flux:button>
            </div>
        </div>
    </flux:modal>
</div>
