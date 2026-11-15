<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\DocumentKind;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show as StageShow;
use App\Livewire\Student\Requests\Create;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
| A credential's documentary requirements are not listed anywhere a request is made or checked,
| and never uploaded: when the evaluator finds one required, Require Office Visit asks for it
| (REQ-04-23, user's decision 2026-11-15).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-04-23 New Request has no documentary requirements section and numbers its sections 1 to 5', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->set('form.credential_type_id', $this->org->tor->id)->html();

    expect($html)->not->toContain('Documentary Requirements')
        ->not->toContain('data-requirement')
        ->not->toContain('Completed clearance')
        ->not->toContain('Submit the originals in person')
        ->toContain('>2. Personal Information</h2>')
        ->toContain('>3. Academic Information</h2>')
        ->toContain('>4. Valid ID</h2>')
        ->toContain('>5. Who Will Transact?</h2>');
});

test('REQ-04-23 the review summary has no documentary requirements', function () {
    $summary = Str::after(Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)
        ->set('form.purpose', 'Employment abroad')
        ->call('review')->assertHasNoErrors()->html(), 'data-summary-title');

    expect($summary)->not->toContain('Documentary Requirements')
        ->not->toContain('Completed clearance')
        ->not->toContain('None for this credential')
        ->toContain('>2. Personal Information</h2>')
        ->toContain('>5. Who Will Transact?</h2>');
});

test('REQ-04-23 a request is submitted without any requirement file', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)
        ->set('form.purpose', 'Employment abroad')
        ->call('review')
        ->set('profile.privacy_consent', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    expect(RequestDocument::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('request-documents'))->toBe([]);
});

test('REQ-04-23 Credential Request Verification has no requirements; they are the Office Visit checkboxes only', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);

    $html = Livewire::actingAs($this->registrar)->test(StageShow::class, ['credentialRequest' => $request->fresh()])->html();
    $card = Str::between($html, 'data-check="credential_request"', 'data-check="identity"');

    expect($card)->not->toContain('Documentary requirements')->not->toContain('Completed clearance')
        ->and(Str::after($html, 'data-office-visit-modal'))->toContain('Completed clearance')->toContain('PSA-authenticated birth certificate');
});

test('REQ-04-23 the Records page and the owner\'s page do not list the requirements', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertDontSee('Documentary requirements')->assertDontSee('Completed clearance');

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertDontSee('Documentary requirements')->assertDontSee('Completed clearance');
});

test('REQ-04-23 no requirement file can be stored: no column and no kind for it', function () {
    expect(Schema::hasColumn('request_documents', 'requirement'))->toBeFalse()
        ->and(array_map(fn (DocumentKind $kind) => $kind->value, DocumentKind::cases()))->toBe(['spa', 'representative_id']);
});
