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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
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

test('REQ-04-02 the form shows requirements, fee, period, offices, conditions and the notice', function () {
    $this->org->tor->update(['special_conditions' => 'Bring the original diploma.']);

    $page = Livewire::actingAs($this->owner)->test(Create::class)
        ->assertSee('Important notice: incomplete requirements')
        ->assertSee('Submitting an online request does not automatically mean that your request has been accepted for processing.')
        ->assertSee('Choose a credential…')
        ->assertDontSee('Copies')
        ->assertDontSee('per copy');

    // No card shows until a credential is chosen.
    expect(credentialCardShown($page->html(), $this->org->tor))->toBeFalse()
        ->and(credentialCardShown($page->html(), $this->org->free))->toBeFalse();

    expect(requirementBlock($page->html(), $this->org->tor))->toContain('PSA-authenticated birth certificate');

    $card = credentialCard($page->html(), $this->org->tor);
    expect($card)->toContain('₱530.00')
        ->toContain('Bring the original diploma.')
        ->toMatch('/7 working days.*from acceptance/s')
        ->toMatch('/Office of the VP for Finance.*\(documentary stamp\).*\(final quality check\)/s');

    $page->set('form.credential_type_id', $this->org->tor->id);
    expect(credentialCardShown($page->html(), $this->org->tor))->toBeTrue()
        ->and(credentialCardShown($page->html(), $this->org->free))->toBeFalse();
});

test('REQ-15-15 the chosen credential\'s details show in the browser at once, without a server round trip', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();

    // The dropdown is not live: Alpine shows the card of the value chosen in the browser.
    expect($html)->toContain('wire:model="form.credential_type_id"')
        ->not->toContain('wire:model.live="form.credential_type_id"')
        ->and(credentialCard($html, $this->org->tor))->toContain("x-show=\"String(\$wire.form.credential_type_id ?? '') === '{$this->org->tor->id}'\"")
        ->and(credentialCard($html, $this->org->free))->toContain("=== '{$this->org->free->id}'")
        ->and(requirementBlock($html, $this->org->tor))->toContain("x-show=\"String(\$wire.form.credential_type_id ?? '') === '{$this->org->tor->id}'\"")
        ->and(requirementBlockShown($html, $this->org->tor))->toBeFalse()
        ->and($html)->toContain('Choose a credential first.');

    // A credential chosen on the server (Edit Request) starts with its requirements shown.
    $html = Livewire::actingAs($this->owner)->test(Create::class)->set('form.credential_type_id', $this->org->tor->id)->html();
    expect(requirementBlockShown($html, $this->org->tor))->toBeTrue()
        ->and(requirementBlockShown($html, $this->org->free))->toBeFalse()
        ->and($html)->toMatch('/<p[^>]*style="display: none"[^>]*data-requirements-placeholder/');

    // The receiving-school field follows the chosen credential the same way.
    $transfer = CredentialType::factory()->withSteps([$this->org->registrarOffice])->create(['requires_receiving_institution' => true]);
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();
    expect(Str::after($html, 'data-receiving-school'))->toContain(Js::from([(string) $transfer->id])->toHtml())
        ->toContain('x-text="transfer ?')
        ->toContain('x-bind:required="transfer"');
});

test('REQ-15-16 the credential dropdown is a searchable list that scrolls within the page', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();
    $menu = Str::between($html, 'wire:model="form.credential_type_id"', 'data-credential-card');

    // Not the browser's own <select>, whose list of every credential ran off the screen.
    expect($html)->not->toMatch('/<select[^>]*form\.credential_type_id/')
        ->and($menu)->toContain('x-modelable="value"')
        ->toContain('aria-haspopup="listbox"')
        ->toContain('role="combobox"')
        ->toContain('aria-label="Search credentials"')
        ->toContain('role="listbox"')
        ->toContain('max-h-64 overflow-y-auto')
        ->toContain('Choose a credential…')
        ->toContain('x-on:keydown.enter.prevent')
        ->toContain('x-on:keydown.escape.prevent.stop')
        // Every active credential is in its list.
        ->toContain($this->org->free->name)
        ->toContain($this->org->tor->name);

    expect(file_get_contents(resource_path('js/app.js')))->toContain("Alpine.data('selectMenu'");

    // A credential already chosen (Edit Request) shows its name on the closed dropdown.
    $html = Livewire::actingAs($this->owner)->test(Create::class)->set('form.credential_type_id', $this->org->tor->id)->html();
    expect(Str::between($html, 'aria-haspopup="listbox"', '</button>'))->toContain($this->org->tor->name);
});

test('REQ-04-03 the tracking number is CR, the two-digit year and the id with at least 5 digits, growing past 99999', function () {
    $date = Carbon::create(2026, 11, 11);

    expect(CredentialRequest::trackingNumber(1, $date))->toBe('CR26-00001')
        ->and(CredentialRequest::trackingNumber(42, $date))->toBe('CR26-00042')
        ->and(CredentialRequest::trackingNumber(99999, $date))->toBe('CR26-99999')
        ->and(CredentialRequest::trackingNumber(100000, $date))->toBe('CR26-100000')
        ->and(CredentialRequest::trackingNumber(1234567, Carbon::create(2027, 1, 1)))->toBe('CR27-1234567');
});

test('REQ-04-03 a submitted request gets a tracking number and snapshots of the profile and fee', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    expect($request->status)->toBe(RequestStatus::Submitted)
        ->and($request->reference_no)->toBe(sprintf('CR%s-%05d', now()->format('y'), $request->id))
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
        ->assertSee('Set on acceptance');
});

test('REQ-04-04 requesters see only their own requests', function () {
    $mine = submitRequest($this->owner, $this->org->tor);
    $theirs = submitRequest(requester($this->org->cba), $this->org->free);

    $this->actingAs($this->owner)->get(route('student.dashboard'))
        ->assertSee($mine->reference_no)->assertDontSee($theirs->reference_no);
    $this->actingAs($this->owner)->get(route('student.requests.show', $theirs))->assertNotFound();
});

test('REQ-04-01 a request is for one document: there is no number of copies', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->assertDontSee('form.copies');

    expect(Schema::hasColumn('credential_requests', 'copies'))->toBeFalse();
});
