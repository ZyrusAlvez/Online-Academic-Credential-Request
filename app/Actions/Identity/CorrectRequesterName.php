<?php

namespace App\Actions\Identity;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Log;

class CorrectRequesterName
{
    /**
     * Correct the locked name of a verified requester; their identity stays verified (REQ-01-28).
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string}  $name
     *
     * @throws DomainException when the requester's name isn't locked
     */
    public function handle(User $requester, User $registrar, array $name): void
    {
        if (! $requester->hasLockedName()) {
            throw new DomainException('Only a verified requester\'s name is corrected by the registrar.');
        }

        $requester->fill([
            'first_name' => $name['first_name'],
            'middle_name' => filled($name['middle_name']) ? $name['middle_name'] : null,
            'last_name' => $name['last_name'],
        ])->save();

        Log::info('Requester name corrected', ['requester_id' => $requester->id, 'registrar_id' => $registrar->id]);
    }
}
