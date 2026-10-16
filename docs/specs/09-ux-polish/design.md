# 09 — UX Polish · Design

Views only: no schema, workflow or authorization changes.

## Password fields

Flux's `viewable` prop on `<flux:input type="password">` adds the eye button (labelled "Toggle
password visibility"). New-password fields get `:description="__('At least 8 characters.')"`, which
matches `Password::defaults()` in `AppServiceProvider`; if that rule changes, change the text.
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

Deactivate menu items get `wire:confirm`, the same native dialog already used to remove a
workflow step and to mark a request released. The confirm text says what deactivation does.
