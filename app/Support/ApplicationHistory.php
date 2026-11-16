<?php

namespace App\Support;

use App\Enums\RequestEventType;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use Illuminate\Support\Collection;

/**
 * A credential's history within its application (REQ-27-07): the application's entries until
 * acceptance, kept on its first credential, then the credential's own.
 */
final class ApplicationHistory
{
    /**
     * @param  Collection<int, RequestEvent>  $leadEvents  the first credential's entries
     * @param  Collection<int, RequestEvent>  $ownEvents  this credential's entries
     * @return Collection<int, RequestEvent>
     */
    public static function for(CredentialRequest $credential, Collection $leadEvents, Collection $ownEvents): Collection
    {
        if ($credential->isLead()) {
            return $ownEvents->values();
        }

        // The first credential's entries before its own acceptance belong to the whole application.
        $accepted = $leadEvents->first(fn (RequestEvent $event): bool => $event->type === RequestEventType::Accepted);
        $shared = $accepted === null ? $leadEvents : $leadEvents->filter(fn (RequestEvent $event): bool => $event->id < $accepted->id);

        return $shared->concat($ownEvents)->sortBy('id')->values();
    }
}
