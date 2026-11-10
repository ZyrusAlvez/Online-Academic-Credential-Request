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
use App\Reports\SignatoryReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

/** The markup of the "why it can't run" dialog only (REQ-17-17). */
function blockedDialog(Testable $page): string
{
    return Str::before(Str::after($page->html(), 'data-decision-blocked'), 'Representative not accepted');
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

    // Reject first, Confirm on its right, on every part.
    expect(preg_match_all('/<button[^>]*?data-(reject|confirm)-button[^>]*>/', $html, $order))->toBeGreaterThan(1)
        ->and(array_chunk($order[1], 2))->each->toBe(['reject', 'confirm']);

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

test('REQ-17-02 the decision buttons are centred under the remarks, Approve on the right', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    $html = handoffPage($request)->html();

    expect($html)->toMatch('/class="flex flex-wrap justify-center gap-2" data-decision-buttons/')
        ->and(preg_match_all('/<button[^>]*?data-(decline|return|approve)-button[^>]*>/', $html, $order))->toBe(3)
        ->and($order[1])->toBe(['decline', 'return', 'approve']);

    // A representative's Verify is right of Reject too.
    $represented = submitRequest($this->owner, $this->org->free);
    workflow()->declareRepresentative($represented, $this->owner, ['full_name' => 'Rosa Reyes']);
    $list = (string) $this->blade('<x-representative-list :representatives="$representatives" reviewable />', ['representatives' => $represented->fresh()->representatives]);

    expect($list)->toContain('verifyRepresentative(')
        ->and(strpos($list, 'confirmRejectRepresentative('))->toBeLessThan(strpos($list, 'verifyRepresentative('));
});

test('REQ-17-17 Approve and Return are always enabled; a blocked one lists every reason in a dialog', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);

    $page = handoffPage($request);

    // Nothing is disabled, and the dialog is on the page.
    expect($page->html())->not->toMatch('/<button[^>]*\sdisabled="disabled"[^>]*data-(approve|return|decline)-button/')
        ->toContain('data-decision-blocked');

    // Approve: the rule, the rejected then the unchecked parts, and how to approve; no Return or Decline.
    $page->call('approve')
        ->assertDispatched('modal-show', name: 'decision-blocked')
        ->assertSet('blockedAction', 'approve')
        ->assertSet('blockedParts', [
            ['label' => 'User Identity Verification', 'state' => 'rejected'],
            ['label' => 'Credential Request Verification', 'state' => 'unchecked'],
            ['label' => 'Academic Record Verification', 'state' => 'unchecked'],
        ])
        ->assertSet('blockers', [])
        ->assertSeeInOrder([
            "Can't approve yet",
            'Approve needs every part of the checklist confirmed.',
            'User Identity Verification', 'rejected',
            'Credential Request Verification', 'not checked yet',
            'Academic Record Verification', 'not checked yet',
            'To approve, change these to Confirm.',
        ]);

    expect(blockedDialog($page))->not->toContain('Return')->not->toContain('Decline')->not->toContain('decline');

    // Return: missing remarks, also marked on the field; what was typed and checked is kept.
    $page->call('returnRequest')
        ->assertHasErrors(['remarks' => 'required'])
        ->assertSet('blockedAction', 'return')
        ->assertSet('blockers', ['Write your remarks; the requester sees them.'])
        ->assertSee("Can't return yet");

    expect($request->fresh()->status)->toBe(RequestStatus::UnderEvaluation)
        ->and($request->checkResults(CheckStage::Evaluation))->toBe([CheckItem::Identity->value => CheckResult::Rejected]);

    // Return with no rejected part and no remarks: both reasons.
    workflow()->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Confirmed);
    handoffPage($request)->call('returnRequest')->assertSet('blockers', [
        'Reject at least one part of the checklist, so the requester knows what to fix.',
        'Write your remarks; the requester sees them.',
    ]);

    // Once the conditions are met, nothing is blocked.
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    handoffPage($request)->set('remarks', 'Bring the original.')->call('returnRequest')
        ->assertNotDispatched('modal-show')->assertSet('blockers', []);
    expect($request->fresh()->status)->toBe(RequestStatus::PendingCompliance);
});

test('REQ-17-17 at the final check one rejected part says to change it to Confirm', function () {
    // Taken through every office to a received final check.
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);
    passOffice($request, $this->dean);
    passOffice($request, $this->vpf);
    passOffice($request, User::factory()->departmentStaff($this->org->chancellor)->create());
    workflow()->acceptReceipt(openVisit($request), $this->registrar);
    $request = $request->fresh();

    workflow()->recordCheck($request, $this->registrar, CheckItem::CredentialDocument, CheckResult::Rejected);
    workflow()->recordCheck($request, $this->registrar, CheckItem::Signatures, CheckResult::Confirmed);

    $page = handoffPage($request)->call('approve')
        ->assertSet('blockedParts', [['label' => 'Credential Document', 'state' => 'rejected']])
        ->assertSeeInOrder(["Can't approve yet", 'Approve needs every part of the checklist confirmed.', 'Credential Document', 'rejected', 'To approve, change it to Confirm.']);

    expect(blockedDialog($page))->not->toContain('decline')->not->toContain('Decline');
});

test('REQ-17-17 at payment validation the missing receipt number is listed with the parts', function () {
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($request);

    handoffPage($request)->call('approve')
        ->assertHasErrors('officialReceiptNo')
        ->assertSet('blockedParts', [
            ['label' => 'Proof of Payment', 'state' => 'unchecked'],
            ['label' => 'Amount and Reference', 'state' => 'unchecked'],
            ['label' => 'Official receipt number', 'state' => 'missing'],
        ])
        ->assertSet('blockers', [])
        ->assertSeeInOrder([
            'Approve needs every part of the checklist confirmed and the official receipt number.',
            'Official receipt number', 'not entered yet',
            'To approve, change the checklist parts to Confirm and enter the official receipt number.',
        ]);

    checkAll($request, $this->registrar, CheckStage::Payment);
    handoffPage($request)->call('approve')
        ->assertSet('blockedParts', [['label' => 'Official receipt number', 'state' => 'missing']])
        ->assertSee('To approve, enter the official receipt number.');
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

    handoffPage($request)->assertDontSee('data-ledger', false)->assertDontSee('Tracking History');
    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSee('data-ledger', false)->assertSee('Tracking History');
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

    inProgressQueue()->assertSee('Forward to College of Computer Studies')->call('forward', $request->id);

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

test('REQ-17-07 forwarding is refused before the office has done its task', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    expect(fn () => workflow()->forwardDocument(openVisit($request), $this->registrar))   // not yet received
        ->toThrow(InvalidTransition::class);

    workflow()->acceptReceipt(openVisit($request), $this->dean);

    expect(fn () => workflow()->forwardDocument(openVisit($request), $this->registrar))   // not yet signed
        ->toThrow(InvalidTransition::class);
    inProgressQueue()->assertDontSee('Forward to')->call('forward', $request->id)->assertForbidden();
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
    $atDean = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($atDean), $this->registrar);
    workflow()->acceptReceipt(openVisit($atDean), $this->dean);
    $notYetAccepted = submitRequest($this->owner, $this->org->tor);

    inProgressQueue()
        ->assertSee($atRegistrar->reference_no)
        ->assertSee($atDean->reference_no)
        ->assertDontSee($notYetAccepted->reference_no)
        ->assertSeeInOrder([$atRegistrar->reference_no, 'Office of the Registrar', 'Received', 'College of Computer Studies'])
        ->assertSeeInOrder([$atDean->reference_no, 'College of Computer Studies', 'Received', 'Office of the VP for Finance']);

    inProgressQueue(['office' => $this->org->ccs->id])->assertSee($atDean->reference_no)->assertDontSee($atRegistrar->reference_no);
});

test('REQ-17-18 a forwarded paper is still with the office that sent it until it is received', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    // Not at the Dean yet: still with the Registrar, going to the Dean next.
    inProgressQueue()
        ->assertSeeInOrder([$request->reference_no, 'Office of the Registrar', 'to College of Computer Studies, not yet received', 'College of Computer Studies'])
        ->assertDontSee('On the way');
    inProgressQueue(['office' => $this->org->registrarOffice->id])->assertSee($request->reference_no);
    inProgressQueue(['office' => $this->org->ccs->id])->assertDontSee($request->reference_no);

    expect($request->fresh()->load('openVisit.department', 'openVisit.fromDepartment')->whereabouts())->toBe('Office of the Registrar');

    // Delivered: now at the Dean.
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    inProgressQueue(['office' => $this->org->ccs->id])->assertSee($request->reference_no);
    inProgressQueue(['office' => $this->org->registrarOffice->id])->assertDontSee($request->reference_no);
    expect($request->fresh()->load('openVisit.department', 'openVisit.fromDepartment')->whereabouts())->toBe('College of Computer Studies');
});

test('REQ-17-18 a paper the Dean forwarded to the Registrar shows at the Dean, next the Registrar, with Accept receipt', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    workflow()->returnVisit(openVisit($request), $this->dean, $this->org->registrarOffice, 'Wrong name', 'Reprint');

    // Sent back to the Registrar for correction, not yet received there.
    expect(openVisit($request))->department_id->toBe($this->org->registrarOffice->id)->status->toBe(VisitStatus::InTransit);

    inProgressQueue()->assertSeeInOrder([$request->reference_no, 'College of Computer Studies', 'to Office of the Registrar, not yet received', 'Office of the Registrar']);

    handoffPage($request)
        ->assertSeeInOrder(['Currently at', 'College of Computer Studies', 'to Office of the Registrar, not yet received', 'Next', 'Office of the Registrar'])
        ->assertSee('Accept receipt');

    // The requester's pages and emails say where the paper is.
    expect($request->fresh()->load('openVisit.department', 'openVisit.fromDepartment')->whereabouts())->toBe('College of Computer Studies');
    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertSee('Currently with College of Computer Studies.');
});

test('REQ-17-18 the dashboard office counts and the office reports count it at the sender', function () {
    $request = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))   // the Admin's dashboard (REQ-18-02)
        ->assertSeeInOrder(['Where Requests Are Waiting', 'Office of the Registrar', '1 forwarded, not yet received']);

    $rows = collect((new DepartmentReport)->rows(new ReportFilter))->keyBy(0);
    expect($rows['Office of the Registrar'][4])->toBe(1)
        ->and($rows->has('College of Computer Studies') ? $rows['College of Computer Studies'][4] : 0)->toBe(0);

    $pending = collect((new SignatoryReport)->rows(new ReportFilter))->firstWhere(1, $request->reference_no);
    expect($pending[0])->toBe('Office of the Registrar')
        ->and($pending[4])->toBe('Forwarded to College of Computer Studies, not yet received');
});

test('REQ-17-11 a done paper shows it has not been forwarded yet', function () {
    $request = preparedAtRegistrar();

    inProgressQueue()->assertSeeInOrder([$request->reference_no, 'Office of the Registrar', 'not yet forwarded']);
});

test('REQ-17-12 the registrar forwards its done papers from In Progress, not those of other offices', function () {
    $mine = preparedAtRegistrar();
    $atDean = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($atDean), $this->registrar);
    workflow()->acceptReceipt(openVisit($atDean), $this->dean);
    workflow()->completeVisit(openVisit($atDean), $this->dean);   // signed, still at the Dean's office

    inProgressQueue()
        ->assertSeeInOrder([$mine->reference_no, 'Office of the Registrar', 'not yet forwarded', 'Forward to College of Computer Studies'])
        ->assertDontSee('Forward to Office of the VP for Finance')
        ->call('forward', $mine->id)->assertDispatched('notify');

    expect(openVisit($mine))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::InTransit);

    inProgressQueue()->call('forward', $atDean->id)->assertForbidden();
    expect(openVisit($atDean))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::Done);
});

test('REQ-17-12 at Accepted for processing one Forward marks the preparation done and sends the paper on', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    inProgressQueue()
        ->assertSeeInOrder([$request->reference_no, 'Office of the Registrar', 'Received', 'Forward to College of Computer Studies'])
        ->assertDontSee('data-next-name', false)   // the button names the next office instead
        ->call('forward', $request->id);

    $prepared = $request->visits()->where('department_id', $this->org->registrarOffice->id)->sole();
    expect($prepared)->status->toBe(VisitStatus::Completed)->done_by->toBe($this->registrar->id)
        ->and($request->steps()->orderBy('position')->first()->status)->toBe(StepStatus::Completed)
        ->and(openVisit($request))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::InTransit)
        ->and($request->fresh()->status)->toBe(RequestStatus::InProgress);
});

test('REQ-17-12 rows where the Registrar acts next open the working page, the others the record', function () {
    $mine = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $elsewhere = preparedAtRegistrar();
    workflow()->forwardDocument(openVisit($elsewhere), $this->registrar);

    inProgressQueue()
        ->assertSee(route('registrar.requests.show', $mine), false)
        ->assertSee(route('records.show', $elsewhere), false);
});

test('REQ-17-13 the working page shows where the Registrar\'s paper is, where it goes next, and the action', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    handoffPage($request)
        ->assertSeeInOrder(['Currently at', 'Office of the Registrar', 'In office', 'Next', 'College of Computer Studies'])
        ->assertSee('Prepared')->assertDontSee('Forward to')
        ->call('completeVisit')
        ->assertSee('Done, not yet forwarded')
        ->assertDontSee('Forward to')->assertDontSee('wire:click="forwardDocument"', false)
        ->assertSee('Forward it to College of Computer Studies from')->assertSee(route('registrar.in-progress'), false)
        ->call('forwardDocument')->assertForbidden();

    expect(openVisit($request)->status)->toBe(VisitStatus::Done);
});

test('REQ-17-16 an office forwards only its own done papers; the registrar only the Registrar\'s', function () {
    $request = preparedAtRegistrar();

    expect($this->dean->can('forward', openVisit($request)))->toBeFalse()          // at the Registrar
        ->and($this->registrar->can('forward', openVisit($request)))->toBeTrue();

    workflow()->forwardDocument(openVisit($request), $this->registrar);
    workflow()->acceptReceipt(openVisit($request), $this->dean);

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh()])
        ->assertDontSee('Forward to')                                             // not done yet
        ->call('completeVisit')
        ->assertSee('Forward to Office of the VP for Finance');

    expect($this->registrar->can('forward', openVisit($request)))->toBeFalse()    // at the Dean's office
        ->and($this->vpf->can('forward', openVisit($request)))->toBeFalse()
        ->and($this->dean->can('forward', openVisit($request)))->toBeTrue();

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh()])->call('forwardDocument');

    expect(openVisit($request))->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit)
        ->and($request->visits()->where('department_id', $this->org->ccs->id)->sole())->completed_by->toBe($this->dean->id);
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
