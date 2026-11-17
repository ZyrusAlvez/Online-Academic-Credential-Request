<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Livewire\Registrar\WalkIn;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\User;
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

test('REQ-27-10 the Registrar records a walk-in application of several credentials', function () {
    $registrar = User::factory()->registrar()->create();
    $page = Livewire::actingAs($registrar)->test(WalkIn::class)
        ->set('form.credential_type_id', $this->org->tor->id)->set('form.purpose', 'Employment')
        ->call('addCredential')
        ->set('form.more.0.credential_type_id', $this->org->free->id)->set('form.more.0.purpose', 'Employment');

    foreach ([
        'first_name' => 'Pedro', 'last_name' => 'Villanueva', 'sex' => 'male', 'email' => 'pedro@example.com', 'contact_number' => '09171234567',
        'academic_status' => AcademicStatus::Graduate->value, 'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science',
        'attended_from' => 2018, 'attended_to' => 2022,
    ] as $field => $value) {
        $page->set("profile.{$field}", $value);
    }

    $page->call('review')->assertHasNoErrors()->set('attested', true)->call('submit')->assertHasNoErrors();

    expect(CredentialRequest::count())->toBe(2)
        ->and(CredentialRequest::where('item_no', 2)->sole()->credential_type_id)->toBe($this->org->free->id);
});

test('REQ-27-10 Edit Request keeps the application\'s credentials', function () {
    $lead = workflow()->submit($this->owner, [
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'],
        ['credential_type_id' => $this->org->free->id, 'purpose' => 'Employment'],
    ]);
    workflow()->startEvaluation($lead, User::factory()->registrar()->create());
    $registrar = User::find($lead->fresh()->evaluated_by);
    workflow()->recordCheck($lead, $registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($lead, $registrar, 'Give the full purpose.');

    $this->actingAs($this->owner)->get(route('student.requests.edit', $lead))->assertOk()->assertDontSee('Add another credential');
});
