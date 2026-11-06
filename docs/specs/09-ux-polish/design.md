# 09 — UX Polish · Design

Views only: no schema, workflow or authorization changes.

## Password fields

Flux's `viewable` prop on `<flux:input type="password">` adds the eye button (labelled "Toggle
password visibility"). New-password fields are followed by `<x-password-checklist model="...">` (REQ-09-02): one
line per rule, a grey circle until the typed password meets it, then a green check. It reads the
field's value from Livewire (`$wire.$get(model)`, which `wire:model` updates as the user types, so
no request is sent per keystroke) and checks it in Alpine with the same tests as
`Password::defaults()` in `AppServiceProvider` (`min(8)->mixedCase()->numbers()->symbols()`):
length in code points, `\p{Lu}`, `\p{Ll}`, `\p{N}`, and `\p{Z}|\p{S}|\p{P}` for a special
character. The server rule stays the real check; if it changes, change the checklist too.
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

## File viewer (REQ-09-14, 15)

- `<x-file-link :href :name :type>` replaces the plain links to uploaded files: ID lists, payment
  proofs, representative documents, Identity Checks and the registrar's request page. It is still
  a real link (`target="_blank"`), so it works without JavaScript and with Ctrl, ⌘ or middle click.
  A plain left click is intercepted by Alpine, which dispatches an `open-file` window event with
  the URL, the name and the MIME type. It is marked `aria-haspopup="dialog"`.
- `<x-file-viewer>`, in the app layout next to `<x-confirm-dialog>`, is a Flux modal (Esc,
  focus trap and focus return come from the native dialog):
  - an image is fitted to the viewport (no zoom: the user dropped it; "Open in new tab" shows
    the full size);
  - a PDF loads in an `<iframe>`, which works because the file routes are same-origin and send no
    frame-blocking header;
  - a spinner with "Loading…" covers the area until the image or iframe fires `load`. An image
    that fails (`error`) shows "This file couldn't be loaded" with the Download button. An image
    already in the cache counts as loaded at once (`$el.complete`), so the spinner never sticks;
  - any other type shows a note and the Download button.
  - The source is cleared on close so the file isn't kept in memory.
- `<x-loading-image>` wraps an `<img>` shown on a page (the payment-proof thumbnail). It shows a
  pulsing placeholder until the image loads, with the same `$el.complete` check (REQ-09-16).
- Downloads: the three file controllers share `ServesPrivateFile::serveFile()`. With `?download=1`
  it answers `Content-Disposition: attachment`, otherwise `inline`, after the same policy check,
  with `no-store` caching and `nosniff`.

The hook relies on `__livewire_confirm`, an internal of Livewire 4 (checked on 4.4.8). If a
Livewire upgrade renames it, the hook stops matching and confirmations fall back to the native
dialog; they don't disappear.
