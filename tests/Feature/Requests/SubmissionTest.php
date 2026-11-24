<?php

use App\Enums\AcademicStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
});

test('REQ-04-01 a requester with a complete profile can submit a request', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)
        ->set('form.purpose', 'Employment abroad')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    expect(CredentialRequest::sole())
        ->user_id->toBe($this->owner->id)
        ->purpose->toBe('Employment abroad');
});

test('REQ-04-01 the receiving school is required when the credential type says so', function () {
    $transfer = CredentialType::factory()->withSteps([$this->org->registrarOffice])->create(['requires_receiving_institution' => true]);

    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $transfer->id)
        ->set('form.purpose', 'Transfer to another school')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasErrors('form.receiving_institution')
        ->set('form.receiving_institution', 'Polytechnic University of the Philippines')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole()->receiving_institution)->toBe('Polytechnic University of the Philippines');
});

test('REQ-04-01 invalid details, inactive types and types without a route are rejected', function (array $input, string $error) {
    $inactive = CredentialType::factory()->inactive()->withSteps([$this->org->registrarOffice])->create();
    $noRoute = CredentialType::factory()->create();

    $input = array_map(fn ($value) => match ($value) {
        'inactive' => $inactive->id,
        'no-route' => $noRoute->id,
        default => $value,
    }, $input);

    Livewire::actingAs($this->owner)->test(Create::class)
        ->set(array_merge(['form.credential_type_id' => $this->org->tor->id, 'form.purpose' => 'Employment'], $input))
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasErrors($error);

    expect(CredentialRequest::count())->toBe(0);
})->with([
    'no purpose' => [['form.purpose' => ''], 'form.purpose'],
    'inactive type' => [['form.credential_type_id' => 'inactive'], 'form.credential_type_id'],
    'type without a route' => [['form.credential_type_id' => 'no-route'], 'form.credential_type_id'],
]);

test('REQ-04-01 the workflow refuses requesters without a complete profile', function () {
    $incomplete = User::factory()->student()->create();

    expect(fn () => submitRequest($incomplete, $this->org->tor))->toThrow(InvalidTransition::class);
});

test('REQ-04-03 the tracking number is lowercase rc, the two-digit year and the id with at least 5 digits, growing past 99999', function () {
    $date = Carbon::create(2026, 11, 11);

    expect(CredentialRequest::trackingNumber(1, $date))->toBe('rc26-00001')
        ->and(CredentialRequest::trackingNumber(42, $date))->toBe('rc26-00042')
        ->and(CredentialRequest::trackingNumber(99999, $date))->toBe('rc26-99999')
        ->and(CredentialRequest::trackingNumber(100000, $date))->toBe('rc26-100000')
        ->and(CredentialRequest::trackingNumber(1234567, Carbon::create(2027, 1, 1)))->toBe('rc27-1234567');
});

test('REQ-04-03 a submitted request gets a tracking number and snapshots of the profile and fee', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    expect($request->status)->toBe(RequestStatus::Submitted)
        // The application's number and the credential's position (REQ-27-01).
        ->and($request->reference_no)->toBe(sprintf('rc%s-%05d-1', now()->format('y'), $request->application_id))
        ->and($request->submitted_at)->not->toBeNull()
        ->and($request->accepted_at)->toBeNull()
        ->and($request->due_at)->toBeNull()
        ->and($request->fee)->toBe('530.00')
        ->and($request->amount_due)->toBe('530.00')
        ->and($request->college_id)->toBe($this->org->ccs->id)
        ->and($request->academic_status)->toBe(AcademicStatus::Graduate)
        ->and($request->program)->toBe($this->owner->program)
        ->and($request->events()->sole()->type)->toBe(RequestEventType::Submitted);

    $this->org->tor->update(['fee' => '999.00']);
    $this->owner->update(['program' => 'Changed later']);
    expect($request->fresh())->fee->toBe('530.00')->program->not->toBe('Changed later');
});

test('REQ-04-03 the requester is told the request was received for evaluation', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertOk()
        ->assertSee('received for evaluation')
        ->assertSee('Set on payment validation');
});

test('REQ-04-04 requesters see only their own requests', function () {
    $mine = submitRequest($this->owner, $this->org->tor);
    $theirs = submitRequest(requester($this->org->cba), $this->org->free);

    $this->actingAs($this->owner)->get(route('student.dashboard'))
        ->assertSee($mine->reference_no)->assertDontSee($theirs->reference_no);
    $this->actingAs($this->owner)->get(route('student.requests.show', $theirs))->assertNotFound();
});
