<?php

use App\Enums\CheckItem;
use App\Enums\Sex;
use App\Livewire\Registrar\Requests\Show;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Support\VerifiedDetails;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Spec 28: the requester's sex and the maiden name captions, and the wording of the statuses and the landing page.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

test('REQ-28-06 each request keeps its own copy of the sex, shown to the registrar', function () {
    $this->owner->update(['sex' => Sex::Female]);
    $request = submitRequest($this->owner, $this->org->tor);

    $this->owner->update(['sex' => Sex::Male]);

    expect($request->fresh())->sex->toBe(Sex::Female);

    workflow()->startEvaluation($request, $this->registrar);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->assertSeeInOrder(['Sex', 'Female']);
});

test('REQ-28-07 changing the sex sends the personal information back for verification', function () {
    $this->owner->update(['sex' => Sex::Female]);
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $verified = fn (): bool => VerifiedDetails::current($this->owner->fresh(), CheckItem::Identity) !== null;

    expect($verified())->toBeTrue();

    $this->owner->update(['sex' => Sex::Male]);
    expect($verified())->toBeFalse()
        ->and(VerifiedDetails::sections($this->owner->fresh())['personal'])->toBeFalse();
});
