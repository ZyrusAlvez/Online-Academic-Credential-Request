<?php

use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Livewire\Department\Queue as OfficeList;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| The department office's lists and request page (spec 24).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
});

afterEach(fn () => Carbon::setTestNow());

/** A TOR prepared and forwarded to the Dean, not yet received there. */
function sentToDean(?User $owner = null): CredentialRequest
{
    $request = acceptRequest(submitRequest($owner ?? test()->owner, test()->org->tor), test()->registrar);
    workflow()->forwardDocument(openVisit($request), test()->registrar);

    return $request->fresh();
}

function officeList(string $queue, ?User $user = null, array $query = []): Testable
{
    return Livewire::withQueryParams($query)->actingAs($user ?? test()->dean)->test(OfficeList::class, ['queue' => $queue]);
}

test('REQ-24-02 Incoming lists papers sent to the office; To Validate those to approve; Outgoing the approved ones', function () {
    $incoming = sentToDean();
    $inOffice = sentToDean();
    workflow()->acceptReceipt(openVisit($inOffice), $this->dean);
    $done = sentToDean();
    workflow()->acceptReceipt(openVisit($done), $this->dean);
    workflow()->completeVisit(openVisit($done), $this->dean);

    officeList('incoming')
        ->assertSeeInOrder(['Request', 'Requester', 'Since'])
        ->assertSee($incoming->reference_no)->assertDontSee($inOffice->reference_no)->assertDontSee($done->reference_no)
        ->assertSee(route('department.incoming.show', $incoming), false);

    officeList('to_validate')
        ->assertSee($inOffice->reference_no)->assertDontSee($done->reference_no)->assertDontSee($incoming->reference_no)
        ->assertSee(route('department.to-validate.show', $inOffice), false);

    officeList('outgoing')
        ->assertSeeInOrder(['Request', 'Requester', 'Next', 'Since'])
        ->assertSee($done->reference_no)->assertDontSee($inOffice->reference_no)->assertDontSee($incoming->reference_no)
        ->assertSeeInOrder([$done->reference_no, 'Office of the VP for Finance'])
        ->assertSee(route('department.outgoing.show', $done), false);

    // Another office sees none of them.
    officeList('incoming', $this->vpf)->assertDontSee($incoming->reference_no);
});

test('REQ-24-05 Incoming acknowledges the ticked documents at once; the button waits for a tick', function () {
    $a = sentToDean();
    $b = sentToDean();
    $c = sentToDean();

    $page = officeList('incoming')
        ->assertSeeHtml('data-select-page')->assertSeeHtml('data-select-row');
    expect($page->html())->toMatch('#<button(?=[^>]*\sdisabled)(?=[^>]*data-acknowledge)[^>]*>#');

    $page->call('togglePage', [openVisit($a)->id, openVisit($b)->id, openVisit($c)->id])->assertCount('selected', 3)
        ->call('togglePage', [openVisit($a)->id, openVisit($b)->id, openVisit($c)->id])->assertCount('selected', 0)
        ->set('selected', [(string) openVisit($a)->id, (string) openVisit($b)->id])
        ->assertSee('Acknowledge receipt (2)')
        ->call('acknowledge')->assertDispatched('notify', message: 'Receipt acknowledged for 2 documents.')
        ->assertSet('selected', []);

    expect(openVisit($a))->status->toBe(VisitStatus::Received)->received_by->toBe($this->dean->id)
        ->and(openVisit($b)->status)->toBe(VisitStatus::Received)
        ->and(openVisit($c)->status)->toBe(VisitStatus::InTransit);

    // Nothing ticked: refused; another office's document: skipped, never received.
    officeList('incoming')->call('acknowledge')->assertHasErrors(['selected']);
    $atVpf = sentToDean();
    passOffice($atVpf, $this->dean);   // now on its way to VP Finance
    officeList('incoming')->set('selected', [(string) openVisit($atVpf)->id, (string) openVisit($c)->id])->call('acknowledge')
        ->assertDispatched('notify', message: 'Receipt acknowledged for 1 document. 1 not acknowledged: already received, or no longer sent here.');
    expect(openVisit($atVpf)->status)->toBe(VisitStatus::InTransit);

    // No checkboxes on To Validate or History, and no acknowledging outside Incoming.
    officeList('to_validate')->assertDontSeeHtml('data-select-row')->assertDontSeeHtml('data-acknowledge');
    officeList('history')->assertDontSeeHtml('data-select-row')->call('acknowledge')->assertNotFound();
    officeList('outgoing')->call('acknowledge')->assertNotFound();
});

test('REQ-24-08 department staff open only the verified proof of a request sent to them', function () {
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($request);
    workflow()->rejectPayment($request->fresh(), $this->registrar, 'Blurry.');
    uploadPayment($request->fresh());
    workflow()->verifyPaymentAndAccept($request->fresh(), $this->registrar);
    workflow()->forwardDocument(openVisit($request), $this->registrar);

    $rejected = $request->paymentProofs()->where('status', PaymentStatus::Rejected)->sole();
    $verified = $request->paymentProofs()->where('status', PaymentStatus::Verified)->sole();

    $page = $this->actingAs($this->dean)->get(route('department.incoming.show', $request))->assertOk();
    $page->assertSee(route('payment-proofs.show', $verified), false)->assertDontSee(route('payment-proofs.show', $rejected), false);

    $this->actingAs($this->dean)->get(route('payment-proofs.show', $verified))->assertOk();
    $this->actingAs($this->dean)->get(route('payment-proofs.show', $rejected))->assertNotFound();
    $this->actingAs($this->vpf)->get(route('payment-proofs.show', $verified))->assertNotFound();   // never sent to VP Finance
});
