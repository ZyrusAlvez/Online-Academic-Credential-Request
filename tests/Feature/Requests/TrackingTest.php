<?php

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
        ->assertSee('(final quality check)')
        ->assertSee('7 working days')
        ->assertSee('Set on payment validation')
        ->assertDontSee('Set on acceptance');

    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($request, $this->registrar);
    passOffice($request, $this->registrar);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
        ->assertSee('College of Computer Studies')
        ->assertSee('Saturday, Oct 17, 2026');
});

test('REQ-06-02 the requester history names offices, never staff, and hides internal entries', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);
    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Missing signature', 'Sign page 2');
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    $page = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
        ->assertSee('Received by College of Computer Studies')
        ->assertSee('Signed by College of Computer Studies')
        ->assertSee('In progress')
        ->assertDontSee('Returned for correction')
        ->assertDontSee('Forwarded to')
        ->assertDontSee('Missing signature');

    // The Evaluator row of Request Details is the one staff name the requester sees (REQ-16-32).
    $outsideEvaluator = preg_replace('/<div[^>]*data-request-evaluator[^>]*>.*?<\/div>/s', '', $page->html());

    expect($page->html())->toContain('data-request-evaluator')
        ->and($outsideEvaluator)->not->toContain('Dina Dean')
        ->and($outsideEvaluator)->not->toContain('Rhea Registrar');
});
