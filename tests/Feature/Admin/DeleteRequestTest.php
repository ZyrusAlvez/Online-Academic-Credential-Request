<?php

use App\Actions\Requests\DeleteRequest;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Student\Dashboard as RequestTracker;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Notifications\DueReminder;
use App\Queries\ActivityLogFilter;
use App\Queries\VisitQueue;
use App\Reports\ReportFilter;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| The Admin deletes a request, which is kept and hidden, and restores it (spec 37).
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->admin = User::factory()->admin()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
});

/** A TOR and a free certification requested together; the TOR accepted and sent on to the Dean. */
function applicationAtDean(): CredentialRequest
{
    $lead = workflow()->submit(test()->owner, [
        ['credential_type_id' => test()->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null],
        ['credential_type_id' => test()->org->free->id, 'purpose' => 'Scholarship application', 'receiving_institution' => null],
    ]);
    acceptRequest($lead, test()->registrar);
    passOffice($lead->fresh(), test()->registrar);

    return $lead->fresh();
}

function recordPage(CredentialRequest $request, ?User $as = null): Testable
{
    return Livewire::actingAs($as ?? test()->admin)->test(RecordShow::class, ['credentialRequest' => CredentialRequest::withTrashed()->findOrFail($request->id)]);
}

function deleteAsAdmin(CredentialRequest $request, string $reason = 'Duplicate of another request'): Testable
{
    return recordPage($request)->call('openDelete')->set('deletionReason', $reason)->call('deleteRequest');
}

test('REQ-37-01 the Admin deletes the whole application with a reason; no one else can', function () {
    $lead = applicationAtDean();

    deleteAsAdmin($lead, '')->assertHasErrors(['deletionReason' => 'required']);
    expect(CredentialRequest::where('application_id', $lead->application_id)->count())->toBe(2);

    deleteAsAdmin($lead)->assertHasNoErrors();

    $credentials = CredentialRequest::onlyTrashed()->where('application_id', $lead->application_id)->get();
    expect($credentials)->toHaveCount(2)
        ->and($credentials->pluck('deleted_by')->unique()->all())->toBe([$this->admin->id])
        ->and($credentials->pluck('deletion_reason')->unique()->all())->toBe(['Duplicate of another request']);

    $other = applicationAtDean();
    recordPage($other, $this->registrar)->assertDontSeeHtml('data-delete-button')->call('openDelete')->assertForbidden();
    expect(fn () => app(DeleteRequest::class)->handle($lead->fresh() ?? $lead, $this->admin, 'Again'))->toThrow(InvalidTransition::class);
});

test('REQ-37-02 a deleted request is gone from lists, queues, reports, the tracker, reminders and the open-request limit', function () {
    $lead = applicationAtDean();
    $openBefore = $this->owner->openRequestCount();
    deleteAsAdmin($lead);

    // The requester: their tracker and the request's page.
    Livewire::actingAs($this->owner)->test(RequestTracker::class)->assertDontSee($lead->application->reference_no);
    $this->actingAs($this->owner)->get(route('student.requests.show', $lead))->assertNotFound();
    expect($this->owner->openRequestCount())->toBe($openBefore - 2);

    // The Registrar's Records and the request's record.
    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)->assertDontSee($lead->application->reference_no);
    $this->actingAs($this->registrar)->get(route('records.show', $lead))->assertNotFound();

    // The Dean's queues and the request's page.
    expect(VisitQueue::open($this->org->ccs->id, VisitStatus::InTransit, VisitStatus::Received)->count())->toBe(0)
        ->and(VisitQueue::history($this->org->registrarOffice->id)->count())->toBe(0);
    $this->actingAs($this->dean)->get(route('department.requests.show', $lead))->assertNotFound();

    // Reports and reminders.
    expect((new ReportFilter)->requests()->count())->toBe(0)
        ->and((new ReportFilter)->visits()->count())->toBe(0)
        ->and((new ReportFilter)->heldVisits()->count())->toBe(0);

    Notification::fake();
    $this->travelTo(now()->addMonths(2));
    $this->artisan('app:send-due-reminders')->assertSuccessful();
    Notification::assertNothingSentTo($this->owner, DueReminder::class);
});

test('REQ-37-02 a deleted request\'s files are the Admin\'s only', function () {
    $lead = applicationAtDean();
    $proof = $lead->paymentProofs()->firstOrFail();
    deleteAsAdmin($lead);

    foreach ([$this->registrar, $this->owner, $this->dean] as $user) {
        $this->actingAs($user)->get(route('payment-proofs.show', $proof))->assertNotFound();
    }
    $this->actingAs($this->admin)->get(route('payment-proofs.show', $proof))->assertOk();
});

test('REQ-37-02 the requester\'s verified details still count after a request is deleted', function () {
    $first = submitRequest($this->owner, $this->org->tor);
    evaluate($first, $this->registrar);   // verifies the ID and the academic record (REQ-16-12)
    deleteAsAdmin($first);

    $next = submitRequest($this->owner, $this->org->free);

    expect($next->checks()->whereNotNull('carried_from_request_id')->count())->toBeGreaterThan(0);
});

test('REQ-37-02 a page opened before the delete cannot act on the request', function () {
    $lead = applicationAtDean();
    $visit = openVisit($lead);
    deleteAsAdmin($lead);

    expect(fn () => workflow()->acceptReceipt($visit, $this->dean))->toThrow(InvalidTransition::class, 'was deleted by the Admin');
});

test('REQ-37-03 deleting keeps the ledger, the files, the visits and the status, and notifies no one', function () {
    $lead = applicationAtDean();
    $status = $lead->status;
    $events = RequestEvent::count();
    $visits = $lead->visits()->count();
    $files = Storage::disk(RequestWorkflow::proofDisk())->allFiles();
    Notification::fake();

    deleteAsAdmin($lead);

    $deleted = CredentialRequest::withTrashed()->findOrFail($lead->id);
    expect($deleted->status)->toBe($status)
        ->and(RequestEvent::count())->toBe($events)
        ->and($deleted->visits()->count())->toBe($visits)
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toBe($files);
    Notification::assertNothingSent();
});

test('REQ-37-04 the Admin lists deleted requests under Deleted and opens one, with who, when and why', function () {
    $lead = applicationAtDean();
    $kept = applicationAtDean();
    deleteAsAdmin($lead, 'Test request');

    Livewire::actingAs($this->admin)->test(RecordsIndex::class)
        ->assertDontSee($lead->application->reference_no)
        ->set('status', 'deleted')
        ->assertSee($lead->application->reference_no)
        ->assertDontSee($kept->application->reference_no);

    // The Registrar gets no Deleted list: the filter is ignored.
    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)->set('status', 'deleted')
        ->assertDontSee($lead->application->reference_no);

    $this->actingAs($this->admin)->get(route('records.show', $lead))->assertOk()
        ->assertSeeHtml('data-deleted-callout')
        ->assertSee('Test request')
        ->assertSee($this->admin->name)
        ->assertSeeHtml('data-restore-button')
        ->assertDontSeeHtml('data-delete-button')
        ->assertDontSeeHtml('data-override-button');
});

test('REQ-37-05 the Admin restores a deleted request and it is back everywhere as it was', function () {
    $lead = applicationAtDean();
    deleteAsAdmin($lead);

    recordPage($lead, $this->registrar)->assertNotFound();
    recordPage($lead)->call('restoreRequest');

    expect(CredentialRequest::where('application_id', $lead->application_id)->count())->toBe(2)
        ->and(CredentialRequest::findOrFail($lead->id))->deleted_by->toBeNull()->deletion_reason->toBeNull();
    $this->actingAs($this->owner)->get(route('student.requests.show', $lead))->assertOk();
    expect(VisitQueue::open($this->org->ccs->id, VisitStatus::InTransit, VisitStatus::Received)->count())->toBe(1);
    workflow()->acceptReceipt(openVisit($lead->fresh()), $this->dean);
});

test('REQ-37-06 deleting and restoring are logged, and the log still finds the request while it is deleted', function () {
    $lead = applicationAtDean();
    deleteAsAdmin($lead, 'Spam');
    recordPage($lead)->call('restoreRequest');
    deleteAsAdmin($lead, 'Spam again');

    $reference = $lead->application->reference_no;
    $entries = ActivityLog::whereIn('action', ['request.deleted', 'request.restored'])->orderBy('id')->get();
    expect($entries->pluck('action')->all())->toBe(['request.deleted', 'request.restored', 'request.deleted'])
        ->and($entries->pluck('reference_no')->unique()->all())->toBe([$reference])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$this->admin->id])
        ->and($entries->first()->description)->toContain('Spam');

    $found = (new ActivityLogFilter(search: $this->owner->first_name))->query()->pluck('action');
    expect($found)->toContain('request.deleted');
});
