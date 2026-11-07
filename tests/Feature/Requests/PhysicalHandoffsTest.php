<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Department\Dashboard as OfficeQueue;
use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Reports\DepartmentReport;
use App\Reports\ReportFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
});

afterEach(fn () => Carbon::setTestNow());

function handoffPage(CredentialRequest $request): Testable
{
    return Livewire::actingAs(test()->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()]);
}

function inProgressQueue(array $query = []): Testable
{
    return Livewire::withQueryParams($query)->actingAs(test()->registrar)->test(Queue::class, ['queue' => 'in_progress']);
}

/** An accepted TOR whose paper the Registrar has prepared but not yet forwarded. */
function preparedAtRegistrar(): CredentialRequest
{
    $request = acceptRequest(submitRequest(test()->owner, test()->org->tor), test()->registrar);
    workflow()->completeVisit(openVisit($request), test()->registrar);

    return $request->fresh();
}

// ── The registrar's stage page ──────────────────────────────────────────────────────────────

test('REQ-17-01 Confirm is green and Reject red, and the chosen one is ringed and pressed', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);

    $html = handoffPage($request)->html();

    // Every part's Confirm is green and its Reject red, chosen or not.
    expect(substr_count($html, 'data-confirm-button'))->toBe(substr_count($html, 'data-reject-button'))->toBeGreaterThan(1)
        ->and(preg_match_all('/<button[^>]*data-confirm-button[^>]*>/', $html, $confirms))->toBeGreaterThan(1)
        ->and(collect($confirms[0])->every(fn ($tag) => str_contains($tag, '--color-green-700')))->toBeTrue()
        ->and(preg_match_all('/<button[^>]*data-reject-button[^>]*>/', $html, $rejects))->toBeGreaterThan(1)
        ->and(collect($rejects[0])->every(fn ($tag) => str_contains($tag, '--color-red-700')))->toBeTrue();

    // Only the rejected identity part's Reject is ringed and pressed.
    $pressed = collect($rejects[0])->filter(fn ($tag) => str_contains($tag, 'aria-pressed="true"'));
    expect($pressed)->toHaveCount(1)
        ->and($pressed->first())->toContain('ring-red-700')->toContain("check('identity', 'rejected')")
        ->and(collect($confirms[0])->contains(fn ($tag) => str_contains($tag, 'aria-pressed="true"')))->toBeFalse();
});

test('REQ-17-02 the decision buttons are centred under the remarks', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    expect(handoffPage($request)->html())
        ->toMatch('/class="flex flex-wrap justify-center gap-2" data-decision-buttons/');
});

test('REQ-17-03 the remarks field has no hint but its rules still apply', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);

    handoffPage($request)
        ->assertDontSee('Optional to approve')
        ->assertDontSee('required to return or decline')
        ->call('returnRequest')->assertHasErrors(['remarks' => 'required'])
        ->call('decline')->assertHasErrors(['remarks' => 'required']);

    expect($request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);
});

test('REQ-17-04 the working page has no ledger; the Records page keeps it', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    handoffPage($request)->assertDontSee('data-ledger', false)->assertDontSee('Ledger');
    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSee('data-ledger', false)->assertSee('Ledger');
});

// ── Where the paper is ──────────────────────────────────────────────────────────────────────

test('REQ-17-05 an accepted paper is at the Registrar, received at once by the accepting registrar', function () {
    Carbon::setTestNow('2026-11-09 09:00:00');
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $visit = openVisit($request);

    expect($visit)
        ->department_id->toBe($this->org->registrarOffice->id)
        ->status->toBe(VisitStatus::Received)
        ->received_by->toBe($this->registrar->id)
        ->and($visit->received_at->toDateTimeString())->toBe('2026-11-09 09:00:00')
        ->and($request->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($request->events()->pluck('type')->all())->toContain(RequestEventType::ReceiptAccepted);

    handoffPage($request)->assertDontSee('Accept receipt')->assertSee('Prepared');
});

test('REQ-17-05 a route that starts at another office sends the paper there, to be received', function () {
    $diploma = CredentialType::factory()->withSteps([[$this->org->vpf, RouteTask::DocumentaryStamp]])->create();
    $request = acceptRequest(submitRequest($this->owner, $diploma), $this->registrar);

    expect(openVisit($request))->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit);
});

test('REQ-17-06 marking done keeps the paper in the office and does not notify the next office', function () {
    Notification::fake();
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    handoffPage($request)->set('completeRemarks', 'Printed')->call('completeVisit')->assertHasNoErrors();

    $visit = $request->visits()->sole();
    expect($visit)
        ->status->toBe(VisitStatus::Done)
        ->done_by->toBe($this->registrar->id)
        ->done_at->not->toBeNull()
        ->completed_at->toBeNull()
        ->remarks->toBe('Printed')
        ->and($request->steps()->orderBy('position')->first()->status)->toBe(StepStatus::Completed)
        ->and($request->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and(openVisit($request)->id)->toBe($visit->id);

    Notification::assertNotSentTo($this->dean, RequestActivity::class);
});

test('REQ-17-07 forwarding records when the paper left, opens the next visit and notifies that office', function () {
    Notification::fake();
    Carbon::setTestNow('2026-11-09 09:00:00');   // Monday
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    workflow()->completeVisit(openVisit($request), $this->registrar);
    Carbon::setTestNow('2026-11-10 15:00:00');   // Tuesday: it leaves the next day

    handoffPage($request)->assertSee('Forward to College of Computer Studies')->call('forwardDocument');

    $visits = $request->visits()->orderBy('id')->get();
    expect($visits[0])
        ->status->toBe(VisitStatus::Completed)
        ->completed_by->toBe($this->registrar->id)
        ->working_days->toBe(1)
        ->and($visits[0]->completed_at->toDateTimeString())->toBe('2026-11-10 15:00:00')
        ->and($visits[1])->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::InTransit)->from_department_id->toBe($this->org->registrarOffice->id)
        ->and($request->fresh()->status)->toBe(RequestStatus::InProgress);

    Notification::assertSentTo($this->dean, RequestActivity::class);
});

test('REQ-17-07 a corrected document is done, then forwarded back to the office that returned it', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned', 'Sign page 2');
    workflow()->acceptReceipt(openVisit($request), $this->dean);

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh()])
        ->assertSee('Corrected')->call('completeVisit')
        ->assertSee('Forward to Office of the VP for Finance')->call('forwardDocument');

    $correction = $request->visits()->where('department_id', $this->org->ccs->id)->orderByDesc('id')->first();
    expect($correction)->status->toBe(VisitStatus::Completed)->done_at->not->toBeNull()
        ->and(openVisit($request))->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit)
        ->and($request->events()->pluck('type')->all())->toContain(RequestEventType::Corrected, RequestEventType::DocumentResubmitted);
});

test('REQ-17-07 forwarding is refused before the task is done', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    expect(fn () => workflow()->forwardDocument(openVisit($request), $this->registrar))
        ->toThrow(InvalidTransition::class);
});

test('REQ-17-07 when the next office is the same office, done moves the paper on at once', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);

    workflow()->completeVisit(openVisit($request), $this->registrar);   // prepared; the final check is also at the Registrar

    expect(openVisit($request))->status->toBe(VisitStatus::Received)
        ->and($request->fresh()->status)->toBe(RequestStatus::ForFinalApproval);
});

test('REQ-17-08 the next office accepts receipt, which records the delivery', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    Carbon::setTestNow(now()->addHours(2));

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh()])->call('acceptReceipt');

    expect(openVisit($request))->status->toBe(VisitStatus::Received)->received_by->toBe($this->dean->id)
        ->and(openVisit($request)->received_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('REQ-17-09 approving the final quality check makes it ready for release at once', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);
    workflow()->completeVisit(openVisit($request), $this->registrar);
    checkAll($request, $this->registrar, CheckStage::FinalCheck);

    workflow()->approveFinalCheck($request->fresh(), $this->registrar);

    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease)
        ->and($request->visits()->get()->pluck('status')->unique()->all())->toBe([VisitStatus::Completed])
        ->and($request->openVisit()->first())->toBeNull();
});

test('REQ-17-10 a done document can no longer be returned for correction', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    workflow()->completeVisit($visit = openVisit($request), $this->dean);

    expect(fn () => workflow()->returnVisit($visit, $this->dean, $this->org->registrarOffice, 'x', 'y'))
        ->toThrow(InvalidTransition::class);
    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh()])
        ->assertDontSee('Return for correction');
});

// ── In Progress ─────────────────────────────────────────────────────────────────────────────

test('REQ-17-11 In Progress lists accepted papers with where they are and where they go next', function () {
    $atRegistrar = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $onTheWay = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($onTheWay), $this->registrar);
    $notYetAccepted = submitRequest($this->owner, $this->org->tor);

    inProgressQueue()
        ->assertSee($atRegistrar->reference_no)
        ->assertSee($onTheWay->reference_no)
        ->assertDontSee($notYetAccepted->reference_no)
        ->assertSeeInOrder([$atRegistrar->reference_no, 'Office of the Registrar', 'Received', 'College of Computer Studies'])
        ->assertSeeInOrder([$onTheWay->reference_no, 'College of Computer Studies', 'On the way since', 'Office of the VP for Finance']);

    inProgressQueue(['office' => $this->org->ccs->id])->assertSee($onTheWay->reference_no)->assertDontSee($atRegistrar->reference_no);
});

test('REQ-17-11 a done paper shows it has not been forwarded yet', function () {
    $request = preparedAtRegistrar();

    inProgressQueue()->assertSeeInOrder([$request->reference_no, 'Office of the Registrar', 'not yet forwarded']);
});

test('REQ-17-12 the Registrar forwards its finished papers from In Progress; other rows open the record', function () {
    $mine = preparedAtRegistrar();
    $elsewhere = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($elsewhere), $this->registrar);

    inProgressQueue()
        ->assertSee(route('registrar.requests.show', $mine), false)
        ->assertSee(route('registrar.records.show', $elsewhere), false)
        ->assertSee('Forward to College of Computer Studies')
        ->call('forward', $mine->id)->assertDispatched('notify');

    expect(openVisit($mine))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::InTransit);

    // A paper held by another office cannot be forwarded by the registrar.
    workflow()->acceptReceipt(openVisit($elsewhere), $this->dean);
    workflow()->completeVisit(openVisit($elsewhere), $this->dean);
    inProgressQueue()->call('forward', $elsewhere->id)->assertForbidden();
});

test('REQ-17-13 the working page shows where the Registrar\'s paper is, where it goes next, and the action', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    handoffPage($request)
        ->assertSeeInOrder(['Currently at', 'Office of the Registrar', 'In office', 'Next', 'College of Computer Studies'])
        ->assertSee('Prepared')->assertDontSee('Forward to')
        ->call('completeVisit')
        ->assertSee('Done, not yet forwarded')->assertSee('Forward to College of Computer Studies');
});

// ── Records and queues ──────────────────────────────────────────────────────────────────────

test('REQ-17-14 the office visits table shows received, done and left with the staff for each', function () {
    Carbon::setTestNow('2026-11-09 09:00:00');
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    Carbon::setTestNow('2026-11-09 11:00:00');
    workflow()->completeVisit(openVisit($request), $this->registrar);
    Carbon::setTestNow('2026-11-09 14:00:00');
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSeeInOrder(['Received', 'Done', 'Left', 'Time spent'])
        ->assertSeeInOrder(['Office of the Registrar', 'Nov 9, 9:00 AM', $this->registrar->name, 'Nov 9, 11:00 AM', $this->registrar->name, 'Nov 9, 2:00 PM', $this->registrar->name, 'Same day', 'Completed'])
        ->assertSeeInOrder(['College of Computer Studies', 'Not yet received']);
});

test('REQ-17-15 a done paper still counts as in the office', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    workflow()->completeVisit(openVisit($request), $this->dean);

    Livewire::actingAs($this->dean)->test(OfficeQueue::class)
        ->assertSee('In our office (1)')
        ->set('tab', 'in_office')->assertSee($request->reference_no)->assertSee('Done, not yet forwarded');

    // The department report's "Pending now" counts it for the Dean's office.
    $row = collect((new DepartmentReport)->rows(new ReportFilter))->firstWhere(0, $this->org->ccs->name);
    expect($row[4])->toBe(1);
});
