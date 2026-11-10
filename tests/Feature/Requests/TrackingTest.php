<?php

use App\Enums\CheckStage;
use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Rhea', 'middle_name' => null, 'last_name' => 'Registrar']);
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create(['first_name' => 'Dina', 'middle_name' => null, 'last_name' => 'Dean']);
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
});

afterEach(fn () => Carbon::setTestNow());

test('REQ-06-01 the requester sees the tracker, the holding office and the expected release date', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertSee('Request submitted and evaluated')
        ->assertSee('Payment verified')
        ->assertSee('Final approval and quality check')
        ->assertSee('7 working days')
        ->assertSee('Set on acceptance');

    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($request, $this->registrar);
    passOffice($request, $this->registrar);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
        ->assertSee('College of Computer Studies')
        ->assertSee('Saturday, Oct 17, 2026');
});

test('REQ-06-01 the tracker is a step bar with the date under each finished milestone', function () {
    $tracker = fn ($request) => $this->blade('<x-request-tracker :request="$request" :events="$events" />', [
        'request' => $request->fresh(), 'events' => $request->events()->get(),
    ]);
    $request = submitRequest($this->owner, $this->org->free);

    $tracker($request)
        ->assertSeeInOrder(['Request submitted and evaluated', '(current)', 'Current step', 'Payment verified', '(not yet)'])
        ->assertSee('aria-current="step"', false)
        ->assertDontSee('bg-green-600', false);

    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($request, $this->registrar);
    Carbon::setTestNow('2026-10-12 14:30:00');
    passOffice($request, $this->registrar);   // prepared; the final check is in the same office, so received at once (REQ-17-07)

    $tracker($request)
        ->assertSeeInOrder([
            'Request submitted and evaluated', '(done)', 'Oct 9, 2026', '10:00 AM',
            'Payment verified', '(done)', 'Oct 9, 2026',
            'Processing in progress', '(done)', 'Oct 12, 2026', '2:30 PM',
            'Final approval and quality check', '(current)', 'Current step',
            'Ready for release', '(not yet)',
        ])
        ->assertSee('bg-green-600', false);

    checkAll($request, $this->registrar, CheckStage::FinalCheck);   // REQ-16-19
    workflow()->completeVisit(openVisit($request), $this->registrar);

    $tracker($request)->assertSeeInOrder(['Final approval and quality check', '(done)', 'Ready for release', '(current)']);
});

test('REQ-06-02 the requester history names offices, never staff, and hides internal entries', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);
    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Missing signature', 'Sign page 2');

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
        ->assertSee('Received by College of Computer Studies')
        ->assertSee('Signed by College of Computer Studies')
        ->assertSee('In progress')
        ->assertDontSee('Returned for correction')
        ->assertDontSee('Forwarded to')
        ->assertDontSee('Missing signature')
        ->assertDontSee('Dina Dean')
        ->assertDontSee('Rhea Registrar');
});

test('REQ-06-03 the registrar sees the full ledger with actors and the office visits table', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);
    workflow()->acceptReceipt(openVisit($request), $this->dean);

    // The ledger is on the Records page (REQ-17-04).
    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSee('Rhea Registrar (Registrar)')
        ->assertSee('Forwarded to College of Computer Studies')
        ->assertSeeInOrder(['Office Visits', 'Office of the Registrar', 'Same day', 'Completed', 'College of Computer Studies', 'In progress', 'In office']);
});

test('REQ-06-04 office staff see the visits and history on their request page', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->assertSee('Office Visits')
        ->assertSee('Tracking History')
        ->assertSee('Prepared by Office of the Registrar')
        ->assertSee('Accept receipt');
});
