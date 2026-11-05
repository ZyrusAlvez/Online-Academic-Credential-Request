# 09 — UX Polish · Design

Views only: no schema, workflow or authorization changes.

## Password fields

Flux's `viewable` prop on `<flux:input type="password">` adds the eye button (labelled "Toggle
password visibility"). New-password fields get `:description="__('At least 8 characters, with an uppercase and a lowercase
letter, a number and a special character.')"`, which matches `Password::defaults()` in
`AppServiceProvider` (`min(8)->mixedCase()->numbers()->symbols()`); if that rule changes, change
the text.
The confirm-password page used `new-password` by mistake; it is now `current-password`.

## Page titles

`partials/head.blade.php` already prints `$title`, and class-based components already set it with
`#[Title]` or `->title()`. It never reached the `<head>`, because `layouts/app` and `layouts/auth`
did not pass `$title` on to the nested `layouts/app/sidebar` and `layouts/auth/simple`
components. Both now forward `:title="$title ?? null"`. The Volt auth and settings pages get
`#[Title]` attributes.

## Copy reference number

`<x-copy-button :value :label>` is a small ghost Flux button. On click, Alpine calls
`navigator.clipboard.writeText(value)` and dispatches the existing `notify` window event, so the
confirmation uses `<x-toast>`. The clipboard API needs a secure context, which every environment
has (`localhost` or `https://`). The button sits beside the reference number in each request
page's header.

## Confirmations

Deactivate menu items get `wire:confirm`, like removing a workflow step, a non-working day, and
verifying an identity or a representative. The confirm text says what the action does.

`wire:confirm` keeps the markup, but the browser's dialog is replaced (REQ-09-13). Livewire's
built-in directive stores `el.__livewire_confirm(action, cancel)` and calls it on click;
`resources/js/app.js` hooks `directive.init` (which runs after the built-in handler) and replaces it
with one that dispatches a `confirm-action` window event carrying the message and the `action`
callback. `<x-confirm-dialog>`, in the app layout next to `<x-toast>`, is a Flux modal that shows
it and runs `action` only on the confirm button; Cancel, Esc or a click outside does nothing.
Optional attributes on the same element style it: `data-confirm-title` (default "Please confirm"),
`data-confirm-label` (default "Confirm") and `data-confirm-variant="danger"` for destructive
actions. On a page without the dialog the hook leaves Livewire's native handler in place, so a
confirmation is never skipped. `wire:confirm.prompt` is not used (it would still be native).

The hook relies on `__livewire_confirm`, an internal of Livewire 4 (checked on 4.4.8). If a
Livewire upgrade renames it, the hook stops matching and confirmations fall back to the native
dialog; they don't disappear.
