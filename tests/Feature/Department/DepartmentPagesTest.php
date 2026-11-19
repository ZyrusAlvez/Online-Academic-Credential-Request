<?php

use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Livewire\Department\Queue as OfficeList;
use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

// ── The lists ──────────────────────────────────────────────────────────────────────────────

test('REQ-24-01 the sidebar shows Incoming, To Validate, Outgoing and History with counts; /department leads to Incoming', function () {
    sentToDean();
    $received = sentToDean();
    workflow()->acceptReceipt(openVisit($received), $this->dean);
    foreach ([sentToDean(), sentToDean()] as $approved) {
        workflow()->acceptReceipt(openVisit($approved), $this->dean);
        workflow()->completeVisit(openVisit($approved), $this->dean);
    }

    $html = $this->actingAs($this->dean)->get(route('department.incoming'))->assertOk()->getContent();
    // The sidebar link's text: its label, then its count.
    $link = function (string $queue) use ($html): string {
        preg_match('#<a[^>]*data-queue-count="'.$queue.'".*?</a>#s', $html, $match);

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($match[0] ?? '')));
    };

    expect($html)->toContain('href="'.route('department.incoming').'"')
        ->toContain('href="'.route('department.to-validate').'"')
        ->toContain('href="'.route('department.outgoing').'"')
        ->toContain('href="'.route('department.history').'"')
        ->not->toContain('Office Queue')
        ->not->toContain('To Forward')
        ->and($link('incoming'))->toBe('Incoming 1')
        ->and($link('to_validate'))->toBe('To Validate 1')
        ->and($link('outgoing'))->toBe('Outgoing 2')
        ->and($link('history'))->toBe('History');

    $this->actingAs($this->dean)->get('/department')->assertRedirect('/department/incoming');
    $this->actingAs($this->dean)->get('/dashboard')->assertRedirect(route('department.incoming'));
});

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

test('REQ-24-03 History lists what the office did, newest first, with who and when', function () {
    Carbon::setTestNow('2026-11-09 09:00:00');
    $signed = sentToDean();
    passOffice($signed, $this->dean);
    Carbon::setTestNow('2026-11-10 09:00:00');
    $returned = sentToDean();
    workflow()->acceptReceipt(openVisit($returned), $this->dean);
    workflow()->returnVisit(openVisit($returned), $this->dean, $this->org->registrarOffice, 'Wrong grade', 'Fix it');
    workflow()->forwardDocument(openVisit($returned), $this->registrar);   // carried back (REQ-32-08)

    officeList('history')
        ->assertSeeInOrder(['Request', 'Requester', 'Action', 'Date'])
        ->assertSeeInOrder([$returned->reference_no, 'Returned to Office of the Registrar', 'Nov 10, 2026', $signed->reference_no, 'Signed', 'Nov 9, 2026', $this->dean->name])
        ->assertSee(route('department.history.show', $signed), false);
});

test('REQ-24-04 each list searches, filters by credential type and sorts by its columns only', function () {
    $other = requester($this->org->ccs);
    $other->update(['first_name' => 'Zz', 'middle_name' => null, 'last_name' => 'Zzyzx']);
    Carbon::setTestNow('2026-11-09 09:00:00');
    $first = sentToDean();
    Carbon::setTestNow('2026-11-09 10:00:00');
    $second = sentToDean($other);
    Carbon::setTestNow();

    officeList('incoming', query: ['search' => $second->reference_no])->assertSee($second->reference_no)->assertDontSee($first->reference_no);
    officeList('incoming', query: ['search' => 'Zzyzx'])->assertSee($second->reference_no)->assertDontSee($first->reference_no);
    officeList('incoming', query: ['type' => (string) $this->org->free->id])->assertSee('No documents match.');
    officeList('incoming', query: ['type' => (string) $this->org->tor->id])->assertSee($first->reference_no)->assertSee($second->reference_no);

    // Since, oldest first by default; clicking flips it.
    officeList('incoming')->assertSeeInOrder([$first->reference_no, $second->reference_no])
        ->call('sortBy', 'since')->assertSet('direction', 'desc')->assertSeeInOrder([$second->reference_no, $first->reference_no]);

    // Requester: "Zz Zzyzx" after the other requester ascending, before it descending.
    officeList('incoming')->call('sortBy', 'requester')->assertSet('sort', 'requester')->assertSet('direction', 'asc')
        ->assertSeeInOrder([$first->reference_no, $second->reference_no])
        ->call('sortBy', 'requester')->assertSeeInOrder([$second->reference_no, $first->reference_no]);

    // Anything else is ignored, or falls back to the default.
    officeList('incoming')->call('sortBy', 'status; drop table users')->assertSet('sort', 'since');
    officeList('incoming', query: ['sort' => 'credential_requests.id', 'direction' => 'sideways'])->assertOk()
        ->assertSeeInOrder([$first->reference_no, $second->reference_no]);
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

// ── The request page ───────────────────────────────────────────────────────────────────────

test('REQ-24-06 after an action the list goes back as the office left it', function () {
    $request = sentToDean();
    $list = route('department.incoming', ['search' => $request->reference_no]);

    Livewire::withHeaders(['Referer' => $list])->actingAs($this->dean)
        ->test(OfficeShow::class, ['credentialRequest' => $request, 'queue' => 'incoming'])
        ->assertSeeHtml('href="'.e($list).'"')
        ->call('acceptReceipt')
        ->assertRedirect($list);
});

test('REQ-24-06 a request opens under its list, an action goes back to that list, and the former address leads there', function () {
    $request = sentToDean();

    $html = $this->actingAs($this->dean)->get(route('department.incoming.show', $request))->assertOk()->getContent();
    expect($html)->toMatch('#<a(?=[^>]*href="'.preg_quote(route('department.incoming'), '#').'")(?=[^>]*\sdata-current(?=[\s>="]))[^>]*>#');

    $this->actingAs($this->dean)->get(route('department.to-validate.show', $request))->assertRedirect(route('department.incoming.show', $request));
    $this->actingAs($this->dean)->get(route('department.requests.show', $request))->assertRedirect(route('department.incoming.show', $request));

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request, 'queue' => 'incoming'])
        ->call('acceptReceipt')->assertRedirect(route('department.incoming'));
    expect(session('notify'))->toBe("Receipt confirmed at {$this->org->ccs->name}.");

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh(), 'queue' => 'to_validate'])
        ->call('completeVisit')->assertRedirect(route('department.to-validate'));

    // Outgoing: no panel on the page; once the Registrar forwards it, it is History (REQ-24-07, REQ-32-05).
    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh(), 'queue' => 'outgoing'])
        ->assertSee('Request Details')->assertDontSee('data-office-action', false)->assertDontSee('Forward to');
    workflow()->forwardDocument(openVisit($request), $this->registrar);
    $this->actingAs($this->dean)->get(route('department.outgoing.show', $request))->assertRedirect(route('department.history.show', $request));

    // Never sent to the office: 404, at either address.
    $elsewhere = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $this->actingAs($this->vpf)->get(route('department.history.show', $elsewhere))->assertNotFound();
    $this->actingAs($this->vpf)->get(route('department.requests.show', $elsewhere))->assertNotFound();
});

test('REQ-24-07 the page shows details, the verified proof and the route, then the office\'s action', function () {
    $request = sentToDean();

    $this->actingAs($this->dean)->get(route('department.incoming.show', $request))->assertOk()
        ->assertSeeInOrder(['Request Details', 'Proof of Payment', 'Route', 'Decision', 'Acknowledge receipt'])
        ->assertDontSee('Time in Each Office')->assertDontSee('Tracking History')->assertDontSee('Record delay reason');

    workflow()->acceptReceipt(openVisit($request), $this->dean);
    $html = $this->actingAs($this->dean)->get(route('department.to-validate.show', $request))->assertOk()
        ->assertSeeInOrder(['Request Details', 'Route', 'Decision', 'Received', 'Remarks', 'data-return-button', 'Return', 'data-approve-button', 'Approve'], false)
        ->assertDontSee('Acknowledge receipt')
        ->assertDontSee('>Signed<', false)
        ->getContent();
    // Remarks has no hint; only the return dialog's corrective action is optional (REQ-05-06).
    expect(Str::between($html, 'wire:submit="completeVisit"', 'data-approve-button'))->not->toContain('Optional');

    passOffice($request, $this->dean);
    $this->actingAs($this->dean)->get(route('department.history.show', $request))->assertOk()
        ->assertSee('Request Details')->assertDontSee('data-office-action', false);
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

test('REQ-24-09 no one records delay reasons', function () {
    $request = sentToDean();
    workflow()->acceptReceipt(openVisit($request), $this->dean);

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->assertDontSee('Record delay reason');

    expect(method_exists(workflow(), 'recordDelay'))->toBeFalse()
        ->and(method_exists(OfficeShow::class, 'recordDelay'))->toBeFalse();
});
