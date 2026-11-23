<?php

use App\Actions\Identity\RemoveSavedIdentityDocument;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\ReviewStatus;
use App\Models\CredentialRequest;
use App\Models\RequestCheck;
use App\Models\RequesterVerification;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Support\VerifiedDetails;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Rita', 'middle_name' => null, 'last_name' => 'Reyes']);
    $this->other = User::factory()->registrar()->create(['first_name' => 'Oscar', 'middle_name' => null, 'last_name' => 'Ocampo']);
    $this->owner = requester($this->org->ccs);
});

function check(CredentialRequest $request, CheckItem $item): ?RequestCheck
{
    return $request->checks()->where('item', $item->value)->first();
}

/** Returned for clarification with only the given parts rejected (REQ-20-03). */
function returnWithRejected(CredentialRequest $request, User $registrar, CheckItem ...$rejected): void
{
    workflow()->startEvaluation($request, $registrar);

    foreach ($rejected as $item) {
        workflow()->recordCheck($request, $registrar, $item, CheckResult::Rejected);
    }

    workflow()->returnForClarification($request, $registrar, 'Please correct this.');
}

// ── REQ-21-01..03: what counts as verified ──────────────────────────────────────────────────

test('REQ-21-01 completing an evaluation records both parts as verified on that request, by that registrar', function () {
    $first = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    foreach (VerifiedDetails::PARTS as $part) {
        expect(VerifiedDetails::current($this->owner, $part))
            ->not->toBeNull()
            ->credential_request_id->toBe($first->id)
            ->verified_by->toBe($this->registrar->id);
    }

    // A later request with the same details keeps the first verification.
    evaluate(submitRequest($this->owner, $this->org->free), $this->other);

    expect(RequesterVerification::query()->where('user_id', $this->owner->id)->pluck('credential_request_id')->unique()->all())->toBe([$first->id])
        ->and(RequesterVerification::query()->where('user_id', $this->owner->id)->pluck('verified_by')->unique()->all())->toBe([$this->registrar->id]);
});

test('REQ-21-01 Confirm Compliance verifies the details too', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->requireOfficeVisit($request, $this->registrar, ['Completed clearance'], 'Bring the original clearance.');
    workflow()->markReceived($request->deficiencies()->sole(), $this->other);
    workflow()->confirmCompliance($request, $this->other);

    expect(VerifiedDetails::current($this->owner, CheckItem::Identity)?->credential_request_id)->toBe($request->id)
        ->and(VerifiedDetails::current($this->owner, CheckItem::AcademicRecord)?->verified_by)->toBe($this->other->id);
});

test('REQ-21-01 details changed since the request was sent are not verified by it', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    $this->owner->update(['contact_number' => '09990001111', 'program' => 'BS Information Technology']);

    evaluate($request, $this->registrar);

    expect(RequesterVerification::query()->where('user_id', $this->owner->id)->exists())->toBeFalse();
});

test('REQ-21-02 a part stays verified while the saved details equal the verified copy, and any change sends it back', function () {
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $verified = fn (CheckItem $part): bool => VerifiedDetails::current($this->owner->fresh(), $part) !== null;
    $middleName = $this->owner->middle_name;

    $this->owner->update(['program' => 'BS Information Technology']);
    expect($verified(CheckItem::AcademicRecord))->toBeFalse()
        ->and($verified(CheckItem::Identity))->toBeTrue();

    $this->owner->update(['program' => 'BS Computer Science', 'middle_name' => 'Changed']);
    expect($verified(CheckItem::AcademicRecord))->toBeTrue()
        ->and($verified(CheckItem::Identity))->toBeFalse();

    // Back to the verified name, then removing the saved ID sends identity back too.
    $this->owner->update(['middle_name' => $middleName]);
    expect($verified(CheckItem::Identity))->toBeTrue();

    app(RemoveSavedIdentityDocument::class)->handle($this->owner);
    expect($verified(CheckItem::Identity))->toBeFalse();
});

test('REQ-21-03 returning a request with a verified part rejected drops that part\'s verification', function () {
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $request = submitRequest($this->owner, $this->org->free);

    returnWithRejected($request, $this->other, CheckItem::Identity);

    expect(VerifiedDetails::verification($this->owner, CheckItem::Identity))->toBeNull()
        ->and(VerifiedDetails::verification($this->owner, CheckItem::AcademicRecord))->not->toBeNull();
});

// ── REQ-21-04, 05: on the request ───────────────────────────────────────────────────────────

test('REQ-21-04 a later request with the same details has both parts confirmed already, by who verified them, then', function () {
    $first = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $verifiedAt = VerifiedDetails::current($this->owner, CheckItem::Identity)->verified_at;

    $second = submitRequest($this->owner, $this->org->free);

    foreach ([CheckItem::Identity, CheckItem::AcademicRecord] as $part) {
        expect(check($second, $part))
            ->result->toBe(CheckResult::Confirmed)
            ->checked_by->toBe($this->registrar->id)
            ->carried_from_request_id->toBe($first->id)
            ->and(check($second, $part)->checked_at->equalTo($verifiedAt))->toBeTrue();
    }

    expect($second->fresh())
        ->identity_status->toBe(ReviewStatus::Verified)
        ->identity_reviewed_by->toBe($this->registrar->id)
        // The rest of the checklist is the evaluator's.
        ->and(check($second, CheckItem::CredentialRequest))->toBeNull();
});

test('REQ-21-04 a part whose details differ is left to the evaluator', function () {
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $this->owner->update(['contact_number' => '09990001111']);

    $second = submitRequest($this->owner, $this->org->free);

    expect(check($second, CheckItem::Identity))->toBeNull()
        ->and($second->fresh()->identity_status)->toBe(ReviewStatus::Pending)
        ->and(check($second, CheckItem::AcademicRecord)?->result)->toBe(CheckResult::Confirmed);
});

test('REQ-21-04 a resubmitted request has its unchecked parts confirmed when they were verified meanwhile', function () {
    $first = submitRequest($this->owner, $this->org->tor);
    $second = submitRequest($this->owner, $this->org->free);
    expect(check($second, CheckItem::Identity))->toBeNull();

    evaluate($first, $this->registrar);
    returnWithRejected($second, $this->other, CheckItem::CredentialRequest);
    workflow()->resubmit($second, $this->owner, 'Fixed the purpose.');

    expect(check($second, CheckItem::Identity)?->carried_from_request_id)->toBe($first->id)
        ->and(check($second, CheckItem::AcademicRecord)?->carried_from_request_id)->toBe($first->id);
});
