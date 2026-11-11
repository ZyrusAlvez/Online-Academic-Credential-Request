# 22 — Help for Requesters · Design

## Pieces

- `App\Enums\HelpTopic` (backed by the URL slug): `label()`, `description()`, `icon()`, in sidebar
  order. `HelpTopic::first()` is where `/student/help` goes.
- Routes, in the `student.` group: `help` redirects to the first topic; `help/{topic}` is
  `App\Livewire\Student\Help`, which turns the slug into a `HelpTopic` or responds 404.
- Views: `livewire/student/help.blade.php` (page header, the topic's questions, the closing line)
  includes `help/<slug>.blade.php`, one per topic, written as `<x-faq-item>`s.
- `<x-faq-item question="…">`: a native `<details>` / `<summary>` pair, so it opens with a click,
  Enter or Space and screen readers announce it as expandable without any script; the summary
  has `cursor-pointer` (project rule).
- `partials/nav.blade.php`: after Request Tracker, `<flux:navlist.group expandable heading="Help">`
  with one item per topic, `:expanded` on `student.help*` routes.

## Content that must not drift

The answers read from the code where a fact is configured: the ID types from
`IdentityDocument::ID_TYPES`, the payment portal from `config('uphsl.payment_url')`, the upload
limit from `RequestDocument::MAX_PER_REQUIREMENT`. Links use `route()`.

The rest of the content follows the specs as of 2026-11-11 (01, 04, 06, 10, 11, 13, 15, 20, 21);
a later change to one of those rules should update the matching answer.
