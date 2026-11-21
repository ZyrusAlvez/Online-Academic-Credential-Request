<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Amparo', 'middle_name' => null, 'last_name' => 'Evaluadora']);
    $this->request = submitRequest($this->owner, $this->org->tor);
});

/** A walk-in recorded by the given registrar (REQ-26-03). */
function walkInBy(User $registrar): CredentialRequest
{
    return workflow()->submitWalkIn($registrar, [
        'credential_type_id' => test()->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null,
    ], [
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'sex' => 'male', 'email' => 'pedro.v@example.com',
        'contact_number' => '09171234567', 'academic_status' => AcademicStatus::Graduate->value, 'college_id' => test()->org->ccs->id,
        'program' => 'BS Computer Science', 'student_number' => '18-1234-567', 'attended_from' => 2018, 'attended_to' => 2022,
    ]);
}

test('REQ-16-29 a record shows the evaluator: who took it up, or who entered the walk-in; none before', function () {
    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $this->request->fresh()])
        ->assertDontSeeHtml('data-record-evaluator');

    workflow()->startEvaluation($this->request, $this->registrar);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $this->request->fresh()])
        ->assertSeeHtml('data-record-evaluator')
        ->assertSeeInOrder(['Evaluator', 'Amparo Evaluadora']);

    $clerk = User::factory()->registrar()->create(['first_name' => 'Walter', 'middle_name' => null, 'last_name' => 'Counterman']);
    $walkIn = walkInBy($clerk);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $walkIn->fresh()])
        ->assertSeeInOrder(['Evaluator', 'Walter Counterman']);
});

test('REQ-16-30 the Records list shows each evaluator and filters by evaluator', function () {
    $other = User::factory()->registrar()->create(['first_name' => 'Bruno', 'middle_name' => null, 'last_name' => 'Otrovaluador']);
    $hers = submitRequest($this->owner, $this->org->free);
    workflow()->startEvaluation($this->request, $this->registrar);
    workflow()->startEvaluation($hers, $other);
    $waiting = submitRequest($this->owner, $this->org->free);

    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)
        ->assertSeeHtml('data-records-evaluator')
        ->assertSeeHtml('data-evaluator-filter')
        ->assertSee('Amparo Evaluadora')->assertSee('Bruno Otrovaluador')
        ->assertSee($waiting->reference_no)
        ->set('evaluator', (string) $other->id)
        ->assertSee($hers->reference_no)
        ->assertDontSee($this->request->reference_no)
        ->assertDontSee($waiting->reference_no);

    // Only people who have evaluated are offered.
    $idle = User::factory()->registrar()->create(['first_name' => 'Ida', 'middle_name' => null, 'last_name' => 'Neverevaluated']);
    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)->assertDontSee($idle->name);
});

test('REQ-16-31 Initial Evaluation filters by the request\'s college on both tabs', function () {
    $cbaOwner = requester($this->org->cba);
    $cba = submitRequest($cbaOwner, $this->org->tor);

    $page = fn (array $query) => Livewire::withQueryParams($query)->actingAs($this->registrar)->test(Queue::class, ['queue' => 'evaluation']);

    $page([])->assertSeeHtml('data-college-filter')->assertSee($this->request->reference_no)->assertSee($cba->reference_no);
    $page(['college' => (string) $this->org->cba->id])->assertSee($cba->reference_no)->assertDontSee($this->request->reference_no);
    $page(['college' => (string) $this->org->ccs->id, 'search' => $cba->reference_no])->assertDontSee($cba->reference_no);

    // The waiting tab too.
    workflow()->startEvaluation($cba, $this->registrar);
    workflow()->recordCheck($cba, $this->registrar, CheckItem::Identity, CheckResult::Rejected);
    workflow()->returnForClarification($cba->fresh(), $this->registrar, 'Blurred ID.');
    $page(['waiting' => true, 'college' => (string) $this->org->cba->id])->assertSee($cba->reference_no);
    $page(['waiting' => true, 'college' => (string) $this->org->ccs->id])->assertDontSee($cba->reference_no);

    // Other queues have no college filter.
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'release'])->assertDontSeeHtml('data-college-filter');
});

test('REQ-16-32 the requester\'s page shows the evaluator once the request is taken up', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request->fresh()])
        ->assertDontSeeHtml('data-request-evaluator');

    workflow()->startEvaluation($this->request, $this->registrar);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request->fresh()])
        ->assertSeeHtml('data-request-evaluator')
        ->assertSeeInOrder(['Evaluator', 'Amparo Evaluadora']);
});
