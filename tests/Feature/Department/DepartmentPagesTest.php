<?php

use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Livewire\Department\Queue as OfficeList;
use App\Livewire\Department\Requests\Show as OfficeShow;
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

// ── The lists ──────────────────────────────────────────────────────────────────────────────

test('REQ-24-01 the sidebar shows Incoming, To Validate, To Forward and History with counts; /department leads to Incoming', function () {
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
        ->toContain('href="'.route('department.to-forward').'"')
        ->toContain('href="'.route('department.history').'"')
        ->not->toContain('Office Queue')
        ->and($link('incoming'))->toBe('Incoming 1')
        ->and($link('to_validate'))->toBe('To Validate 1')
        ->and($link('to_forward'))->toBe('To Forward 2')
        ->and($link('history'))->toBe('History');

    $this->actingAs($this->dean)->get('/department')->assertRedirect('/department/incoming');
    $this->actingAs($this->dean)->get('/dashboard')->assertRedirect(route('department.incoming'));
});

test('REQ-24-02 Incoming lists papers sent to the office; To Validate those to approve; To Forward the approved ones', function () {
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

    officeList('to_forward')
        ->assertSeeInOrder(['Request', 'Requester', 'Next', 'Since'])
        ->assertSee($done->reference_no)->assertDontSee($inOffice->reference_no)->assertDontSee($incoming->reference_no)
        ->assertSeeInOrder([$done->reference_no, 'Office of the VP for Finance'])
        ->assertSee(route('department.to-forward.show', $done), false);

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
    officeList('to_forward')->call('acknowledge')->assertNotFound();
});

test('REQ-24-10 To Forward sends the ticked approved documents on at once; the button waits for a tick', function () {
    $approved = function (): CredentialRequest {
        $request = sentToDean();
        workflow()->acceptReceipt(openVisit($request), $this->dean);
        workflow()->completeVisit(openVisit($request), $this->dean);

        return $request;
    };
    $a = $approved();
    $b = $approved();
    $notYet = sentToDean();
    workflow()->acceptReceipt(openVisit($notYet), $this->dean);   // received, not approved

    $page = officeList('to_forward')->assertSeeHtml('data-select-page')->assertSeeHtml('data-select-row');
    expect($page->html())->toMatch('#<button(?=[^>]*\sdisabled)(?=[^>]*data-forward-selected)[^>]*>#');

    $page->set('selected', [(string) openVisit($a)->id, (string) openVisit($b)->id])
        ->assertSee('Forward (2)')
        ->call('forward')->assertDispatched('notify', message: 'Forwarded 2 documents.')
        ->assertSet('selected', []);

    foreach ([$a, $b] as $request) {
        expect(openVisit($request))->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit)
            ->and($request->visits()->where('department_id', $this->org->ccs->id)->sole())->completed_by->toBe($this->dean->id);
    }
    officeList('incoming', $this->vpf)->assertSee($a->reference_no)->assertSee($b->reference_no);

    // Not approved, or another office's: skipped, never forwarded.
    $c = $approved();
    officeList('to_forward')->set('selected', [(string) openVisit($notYet)->id, (string) openVisit($a)->id, (string) openVisit($c)->id])->call('forward')
        ->assertDispatched('notify', message: 'Forwarded 1 document. 2 not forwarded: already forwarded, or no longer here.');
    expect(openVisit($notYet)->status)->toBe(VisitStatus::Received)
        ->and(openVisit($a)->status)->toBe(VisitStatus::InTransit)
        ->and(openVisit($c)->department_id)->toBe($this->org->vpf->id);

    officeList('to_forward')->call('forward')->assertHasErrors(['selected']);
    officeList('incoming')->call('forward')->assertNotFound();
});

test('REQ-24-10 each To Forward row is a Forward to <next office> button for that document', function () {
    $request = sentToDean();
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    workflow()->completeVisit($visit = openVisit($request), $this->dean);

    officeList('to_forward')
        ->assertSeeInOrder([$request->reference_no, 'data-forward-row', 'Forward to Office of the VP for Finance'], false)
        ->call('forwardOne', $visit->id)->assertDispatched('notify', message: 'Forwarded to Office of the VP for Finance. The office confirms when it arrives.')
        ->assertDontSee($request->reference_no);

    expect(openVisit($request))->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit);

    // Another office's document, or outside To Forward: refused.
    $other = sentToDean();
    workflow()->acceptReceipt(openVisit($other), $this->dean);
    workflow()->completeVisit($otherVisit = openVisit($other), $this->dean);
    officeList('to_forward', $this->vpf)->call('forwardOne', $otherVisit->id)->assertNotFound();
    officeList('to_validate')->call('forwardOne', $otherVisit->id)->assertNotFound();
    expect(openVisit($other)->status)->toBe(VisitStatus::Done);
});

// ── The request page ───────────────────────────────────────────────────────────────────────

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

    // To Forward: no panel on the page; the row forwards it, then it is History (REQ-24-07, 10).
    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request->fresh(), 'queue' => 'to_forward'])
        ->assertSee('Request Details')->assertDontSee('data-office-action', false)->assertDontSee('Forward to');
    officeList('to_forward')->call('forwardOne', $request->visits()->where('department_id', $this->org->ccs->id)->sole()->id);
    $this->actingAs($this->dean)->get(route('department.to-forward.show', $request))->assertRedirect(route('department.history.show', $request));

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
    $this->actingAs($this->dean)->get(route('department.to-validate.show', $request))->assertOk()
        ->assertSeeInOrder(['Request Details', 'Route', 'Decision', 'Received', 'Remarks', 'data-return-button', 'Return', 'data-approve-button', 'Approve'], false)
        ->assertDontSee('Acknowledge receipt')->assertDontSee('Optional')
        ->assertDontSee('>Signed<', false);

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
