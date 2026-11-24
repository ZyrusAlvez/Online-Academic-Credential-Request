<?php

use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
});

test('REQ-27-10 New Request adds and removes credentials, refuses one twice, and submits one application', function () {
    $page = Livewire::actingAs($this->owner)->test(Create::class)
        ->assertSee('Add another credential')
        ->set('form.credential_type_id', $this->org->tor->id)->set('form.purpose', 'Employment')
        ->call('addCredential')
        ->assertSeeHtml('data-credential-entry="2"')
        ->set('form.more.0.credential_type_id', $this->org->tor->id)->set('form.more.0.purpose', 'Further studies')
        ->call('review')
        ->assertHasErrors(['form.more.0.credential_type_id' => 'not_in']);

    $page->set('form.more.0.credential_type_id', $this->org->free->id)
        ->call('addCredential')->call('removeCredential', 1)
        ->assertCount('form.more', 1)
        ->call('review')->assertHasNoErrors();

    // The summary: each credential, then the total.
    expect(Str::after($page->html(), 'data-summary-title'))
        ->toMatch('/1\. What Do You Need\?.*Transcript of Records.*Credential 2.*Certification of Enrollment.*Total.*530\.00/s');

    $page->set('profile.privacy_consent', true)
        ->call('submit')->assertHasNoErrors();

    $requests = CredentialRequest::orderBy('item_no')->get();
    expect($requests)->toHaveCount(2)
        ->and($requests->pluck('application_id')->unique())->toHaveCount(1)
        ->and($requests->pluck('credential_type_id')->all())->toBe([$this->org->tor->id, $this->org->free->id])
        ->and($requests[1]->purpose)->toBe('Further studies');
});

test('REQ-27-10 each added credential is checked like the first', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)->set('form.purpose', 'Employment')
        ->call('addCredential')
        ->call('review')
        ->assertHasErrors(['form.more.0.credential_type_id', 'form.more.0.purpose']);
});
