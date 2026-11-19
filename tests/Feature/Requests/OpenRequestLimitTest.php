<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\RequestStatus;
use App\Livewire\Student\Requests\Create;
use App\Livewire\Student\Requests\Edit;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
});

/** Give the owner this many open online requests. */
function openRequests(int $count): void
{
    foreach (range(1, $count) as $i) {
        submitRequest(test()->owner, test()->org->free);
    }
}

/** New Request with one credential filled in. */
function newRequestPage(): Testable
{
    return Livewire::actingAs(test()->owner)->test(Create::class)
        ->set('form.credential_type_id', test()->org->tor->id)
        ->set('form.purpose', 'Employment');
}

test('REQ-33-01 open online requests count one per credential; final ones and walk-ins do not', function () {
    openRequests(2);
    workflow()->submit($this->owner, [
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'],
        ['credential_type_id' => $this->org->free->id, 'purpose' => 'Employment'],
    ]);
    $cancelled = submitRequest($this->owner, $this->org->free);
    workflow()->cancel($cancelled, $this->owner);
    walkIn(submitRequest($this->owner, $this->org->free));

    expect($cancelled->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($this->owner->openRequestCount())->toBe(4)
        ->and($this->owner->openRequestRoom())->toBe(1)
        ->and(CredentialRequest::OPEN_LIMIT)->toBe(5);
});

test('REQ-33-02 with five open requests New Request is refused on review and on submit, saving nothing', function () {
    openRequests(5);

    newRequestPage()->call('review')
        ->assertHasErrors('form.credential_type_id')
        ->assertSet('reviewing', false)
        ->assertSee('You have 5 open requests, the most allowed at a time.');

    expect(CredentialRequest::count())->toBe(5);
});

test('REQ-33-02 more credentials than the room left are refused; within it the request goes through', function () {
    openRequests(3);

    $page = newRequestPage()
        ->call('addCredential')
        ->set('form.more.0.credential_type_id', $this->org->free->id)->set('form.more.0.purpose', 'Employment')
        ->call('review')->assertHasNoErrors();

    // Two more is the room left: a third is not offered.
    $page->call('addCredential')->assertCount('form.more', 1);

    $page->set('profile.privacy_consent', true)->call('submit')->assertHasNoErrors();
    expect($this->owner->openRequestCount())->toBe(5);
});

test('REQ-33-03 submit checks the limit again, so a request made meanwhile is not exceeded', function () {
    openRequests(4);

    $page = newRequestPage()->call('review')->assertHasNoErrors()->set('profile.privacy_consent', true);

    // Another submission from the same account lands between review and submit.
    openRequests(1);

    $page->call('submit')->assertHasErrors('form.credential_type_id')->assertNoRedirect();
    expect(CredentialRequest::count())->toBe(5);
});

test('REQ-33-04 New Request warns only when the requester tries, and offers only the credentials it has room for', function () {
    openRequests(4);
    newRequestPage()
        ->assertDontSeeHtml('data-add-credential')
        ->call('addCredential')->assertCount('form.more', 0);

    // Full: nothing until they try.
    openRequests(1);
    newRequestPage()
        ->assertDontSee('the most allowed at a time')
        ->call('review')
        ->assertSee('the most allowed at a time');

    $fresh = requester($this->org->ccs);
    Livewire::actingAs($fresh)->test(Create::class)->assertSeeHtml('data-add-credential');
});

test('REQ-33-05 Edit Request resubmits at the limit, and the Registrar records walk-ins for a full account', function () {
    openRequests(4);
    $lead = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($lead, User::factory()->registrar()->create());
    $registrar = User::findOrFail($lead->fresh()->evaluated_by);
    workflow()->recordCheck($lead, $registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($lead, $registrar, 'Give the full purpose.');

    Livewire::actingAs($this->owner)->test(Edit::class, ['credentialRequest' => $lead->fresh()])
        ->assertDontSeeHtml('data-request-limit')
        ->set('form.purpose', 'Further studies')
        ->call('review')->assertHasNoErrors()
        ->set('profile.privacy_consent', true)
        ->call('submit')->assertHasNoErrors();

    expect($lead->fresh()->status)->toBe(RequestStatus::Submitted);

    $walkIn = workflow()->submitWalkIn($registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'sex' => 'male', 'maiden_name' => null,
        'email' => $this->owner->email, 'contact_number' => '09171234567', 'academic_status' => AcademicStatus::Graduate->value,
        'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science', 'student_number' => null,
        'attended_from' => 2018, 'attended_to' => 2022,
    ]);

    expect($walkIn->user_id)->toBe($this->owner->id)
        ->and($this->owner->openRequestCount())->toBe(5);
});
