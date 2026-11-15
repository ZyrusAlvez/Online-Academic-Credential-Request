<?php

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
| Documentary requirements are submitted in person at the Office of the Registrar: the app lists
| them and never takes a file for them (REQ-04-22, the client's workflow III-B and III-C).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-04-22 New Request lists the chosen credential\'s requirements, to submit in person, with no upload', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();
    $block = requirementBlock($html, $this->org->tor);

    expect($block)->toContain('data-requirement-list')
        ->toContain('Submit the originals in person at the Office of the Registrar.')
        ->toContain('<li>Completed clearance</li>')
        ->toContain('<li>PSA-authenticated birth certificate</li>')
        ->not->toContain('type="file"')
        ->not->toContain('Upload copies')
        ->not->toContain('Choose files');

    // A credential without requirements says so.
    expect(requirementBlock($html, $this->org->free))
        ->not->toContain('data-requirement-list')
        ->toContain('No documentary requirements.');
});

test('REQ-04-22 the review summary lists the requirements by name, without files', function () {
    $summary = Str::after(Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)
        ->set('form.purpose', 'Employment abroad')
        ->call('review')->assertHasNoErrors()->html(), 'data-summary-title');

    $section = Str::between($summary, '2. Documentary Requirements', '3. ');

    expect($section)->toContain('Requirements')
        ->toContain('Completed clearance')
        ->toContain('PSA-authenticated birth certificate')
        ->not->toContain('No files');
});

test('REQ-04-22 a request is submitted without any requirement file', function () {
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

test('REQ-04-22 the evaluator sees the requirements by name under Credential Request Verification', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    $html = Livewire::actingAs($this->registrar)->test(StageShow::class, ['credentialRequest' => $request->fresh()])->html();
    $card = Str::between($html, 'data-check="credential_request"', 'data-check="identity"');

    expect($card)->toContain('data-requirement-list')
        ->toMatch('#<dt class="text-zinc-600">Documentary requirements</dt>\s*<dd[^>]*>.*?<li>Completed clearance</li>\s*<li>PSA-authenticated birth certificate</li>#s')
        ->not->toContain('No file uploaded')
        ->and(Str::after($html, 'data-check="academic_record"'))->not->toContain('data-requirement-list');
});

test('REQ-04-22 the Records page and the owner\'s page list the requirements by name', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSeeHtml('data-requirement-list')->assertSee('Documentary requirements')->assertSee('Completed clearance')->assertDontSee('No file uploaded');

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertSeeHtml('data-requirement-list')->assertSee('Documentary requirements')->assertSee('Completed clearance')->assertDontSee('No file uploaded');

    // A credential without requirements has no such row.
    $free = submitRequest($this->owner, $this->org->free);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $free])->assertDontSeeHtml('data-requirement-list');
});

test('REQ-04-22 no requirement file can be stored: no column and no kind for it', function () {
    expect(Schema::hasColumn('request_documents', 'requirement'))->toBeFalse()
        ->and(array_map(fn (DocumentKind $kind) => $kind->value, DocumentKind::cases()))->toBe(['spa', 'representative_id']);
});
