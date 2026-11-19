<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * A working page opened from a list goes back to that list, as the user left it (its tab, search,
 * filters and page, kept in the address), once a decision takes the request out of it
 * (REQ-16-26, REQ-24-06, REQ-25-05).
 */
trait ReturnsToList
{
    /** The list the page was opened from, with its query string, or the plain list. */
    #[Locked]
    public string $listUrl = '';

    /**
     * Remember the list: the previous address when it is that list on this site, else the plain
     * list. Only the same host and path are accepted, so the Referer cannot send anyone elsewhere.
     */
    protected function rememberList(string $listRoute): void
    {
        $list = route($listRoute);
        $previous = url()->previous();

        $this->listUrl = self::isSameList($previous, $list) ? $previous : $list;
    }

    /** Back to the list with the decision's message. */
    protected function backToList(string $success): void
    {
        session()->flash('notify', $success);
        $this->redirect($this->listUrl, navigate: true);
    }

    private static function isSameList(string $url, string $list): bool
    {
        $candidate = parse_url($url);
        $target = parse_url($list);

        if ($candidate === false || $target === false) {
            return false;
        }

        return ($candidate['host'] ?? null) === ($target['host'] ?? null)
            && rtrim($candidate['path'] ?? '', '/') === rtrim($target['path'] ?? '', '/');
    }
}
