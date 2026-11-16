<?php

use App\Enums\CheckStage;
use App\Livewire\Cashier\Requests\Show as CashierShow;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\Requests\Show;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

/** A TOR (paid) and a certification (free) in one application; returns its first credential. */
function torAndCertification(): CredentialRequest
{
    return workflow()->submit(test()->owner, [
        ['credential_type_id' => test()->org->tor->id, 'purpose' => 'Employment'],
        ['credential_type_id' => test()->org->free->id, 'purpose' => 'Scholarship application'],
    ]);
}

function secondOf(CredentialRequest $lead): CredentialRequest
{
    return CredentialRequest::where('application_id', $lead->application_id)->where('item_no', 2)->sole();
}

test('REQ-27-06 the Request Tracker lists one row per application with each credential, searchable by any of them', function () {
    $lead = torAndCertification();
    $single = submitRequest($this->owner, $this->org->tor);
    $applicationNo = $lead->application->reference_no;

    Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->assertSeeHtml('href="'.route('student.requests.show', $lead).'"')
        ->assertDontSeeHtml('href="'.route('student.requests.show', secondOf($lead)).'"')
        ->assertSee($applicationNo)->assertSee('Certification of Enrollment')
        ->assertSee($single->reference_no)
        ->set('search', 'Certification')
        ->assertSee($applicationNo)->assertDontSee($single->reference_no)
        ->set('search', secondOf($lead)->reference_no)
        ->assertSee($applicationNo);
});

test('REQ-27-07 the request page shows the application, then a tab per credential; a credential\'s address opens its tab', function () {
    $lead = torAndCertification();
    $other = secondOf($lead);

    Livewire::actingAs($this->owner)->test(Show::class, ['credentialRequest' => $lead])
        ->assertSee($lead->application->reference_no)
        ->assertSeeHtml('data-credential-tabs')
        ->assertSeeInOrder(['Transcript of Records', 'Certification of Enrollment'])
        ->assertSet('tab', 1)
        ->set('tab', 2)
        ->assertSeeHtml('data-credential-tab="2"')
        ->assertSee('Scholarship application');

    Livewire::actingAs($this->owner)->test(Show::class, ['credentialRequest' => $other])
        ->assertSet('tab', 2)
        ->assertSet('credentialRequest.id', $lead->id);

    // One credential: no tabs.
    Livewire::actingAs($this->owner)->test(Show::class, ['credentialRequest' => submitRequest($this->owner, $this->org->tor)])
        ->assertDontSeeHtml('data-credential-tabs');
});

test('REQ-27-07 each tab has its own progress and history: the application\'s until acceptance, then the credential\'s', function () {
    $lead = acceptRequest(torAndCertification(), $this->registrar);

    $page = Livewire::actingAs($this->owner)->test(Show::class, ['credentialRequest' => secondOf($lead)]);
    $page->assertSee('Request received for evaluation')->assertSee('Accepted for processing');
});

test('REQ-27-08 Records lists one row per application; its record has a tab per credential', function () {
    $lead = torAndCertification();

    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)
        ->assertSee($lead->application->reference_no)
        ->assertSee('Transcript of Records')->assertSee('Certification of Enrollment')
        ->assertDontSeeHtml('href="'.route('records.show', secondOf($lead)).'"');

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => secondOf($lead)])
        ->assertSet('tab', 2)
        ->assertSeeHtml('data-credential-tabs')
        ->assertSee('Scholarship application')
        ->assertSee('Request received for evaluation');
});

test('REQ-27-09 evaluation, payment validation and the payment instructions list every credential and the total', function () {
    $lead = torAndCertification();
    workflow()->startEvaluation($lead, $this->registrar);

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $lead->fresh()])
        ->assertSeeHtml('data-application-credentials')
        ->assertSeeInOrder(['Transcript of Records', '₱530.00', 'Certification of Enrollment', 'None']);

    checkAll($lead, $this->registrar, CheckStage::Evaluation);
    workflow()->approveEvaluation($lead, $this->registrar);

    Livewire::actingAs($this->owner)->test(Show::class, ['credentialRequest' => $lead->fresh()])
        ->assertSeeHtml('data-application-total')->assertSee('Amount due: ₱530.00');

    uploadPayment($lead->fresh());
    Livewire::actingAs(User::factory()->cashier()->create())->test(CashierShow::class, ['credentialRequest' => $lead->fresh()])
        ->assertSeeHtml('data-application-credentials')->assertSee('Certification of Enrollment');
});
