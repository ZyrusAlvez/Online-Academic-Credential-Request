<?php

use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        ->assertSee('(final quality check)')
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
            'Office of the Registrar', '(prepare)', '(done)', 'Oct 12, 2026', '2:30 PM',
            'Office of the Registrar', '(final quality check)', '(current)', 'Current step',
            'Ready for release', '(not yet)',
        ])
        ->assertSee('bg-green-600', false);

    workflow()->completeVisit(openVisit($request), $this->registrar);

    $tracker($request)->assertSeeInOrder(['(final quality check)', '(done)', 'Ready for release', '(current)']);
});

/**
 * The tracker's step bar (REQ-06-17), as rendered for the requester.
 */
function stepBar(string $html): string
{
    return Str::between($html, 'data-step-bar', '</ol>');
}

test('REQ-06-17 before acceptance the step bar has every office of the credential\'s route, all still to come', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    $html = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])->html();
    $bar = stepBar($html);

    expect(substr_count($bar, 'data-step-state='))->toBe(8)   // submitted, payment, 5 offices, ready
        ->and(substr_count($bar, 'data-step-state="upcoming"'))->toBe(7)
        ->and(Str::of($bar)->stripTags()->squish()->toString())->toMatch(
            '/Request submitted and evaluated.*Payment verified'
            .'.*Office of the Registrar \(prepare\).*Dean, College of Computer Studies \(sign\).*Office of the VP for Finance \(documentary stamp\)'
            .'.*Office of the Chancellor \(sign\).*Office of the Registrar \(final quality check\).*Ready for release/'
        )
        ->and($html)->not->toContain('Offices That Process It')
        ->and($html)->not->toContain('Processing in progress');
});

test('REQ-06-17 once accepted each office in the step bar shows whether it is done, with the date, current or still to come', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($request, $this->registrar);
    Carbon::setTestNow('2026-10-12 14:30:00');
    passOffice($request, $this->registrar);
    passOffice($request, $this->dean);

    $bar = stepBar(Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])->html());

    expect(Str::of($bar)->stripTags()->squish()->toString())->toMatch(
        '/Office of the Registrar \(prepare\) \(done\) Oct 12, 2026 · 2:30 PM'
        .'.*Dean, College of Computer Studies \(sign\) \(done\) Oct 12, 2026'
        .'.*Office of the VP for Finance \(documentary stamp\) \(current\) Current step'
        .'.*Office of the Chancellor \(sign\) \(not yet\)'
        .'.*Office of the Registrar \(final quality check\) \(not yet\)/'
    )
        ->and(substr_count($bar, 'data-step-state="done"'))->toBe(4)
        ->and(substr_count($bar, 'aria-current="step"'))->toBe(1)
        ->and($bar)->toContain('bg-green-600');
});

test('REQ-06-17 a cancelled or declined request shows no office as current', function () {
    $cancelled = submitRequest($this->owner, $this->org->tor);
    workflow()->cancel($cancelled, $this->owner);

    $declined = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);
    passOffice($declined, $this->registrar);   // now at the final quality check
    workflow()->decline($declined->fresh(), $this->registrar, 'Records do not match');

    foreach ([$cancelled, $declined] as $request) {
        $bar = stepBar(Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])->html());

        expect($bar)->toContain('(final quality check)')
            ->and($bar)->not->toContain('aria-current="step"')
            ->and($bar)->not->toContain('data-step-state="current"');
    }
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
