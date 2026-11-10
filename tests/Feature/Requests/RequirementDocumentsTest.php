<?php

use App\Enums\DocumentKind;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show as StageShow;
use App\Livewire\Student\Requests\Create;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

/** New Request for the TOR, whose requirements are "Completed clearance" and the PSA birth certificate. */
function torRequestForm(): Testable
{
    return Livewire::actingAs(test()->owner)->test(Create::class)
        ->set('form.credential_type_id', test()->org->tor->id)
        ->set('form.purpose', 'Employment abroad');
}

function submitRequestForm(Testable $form): Testable
{
    return $form->set('profile.privacy_consent', true)->call('review')->call('submit');
}

function fakePdf(string $name = 'clearance.pdf', int $kilobytes = 200): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

// ── REQ-04-18: the fields on New Request ────────────────────────────────────────────────────

test('REQ-04-18 each requirement of a credential has its own file field, for several files, not marked optional', function () {
    $block = requirementBlock(Livewire::actingAs($this->owner)->test(Create::class)->html(), $this->org->tor);
    $tor = $this->org->tor->id;

    expect($block)->toContain('data-requirement-uploads')
        ->toContain("wire:model=\"requirementFiles.{$tor}.0\"")
        ->toContain("wire:model=\"requirementFiles.{$tor}.1\"")
        ->toContain('Completed clearance')
        ->toContain('PSA-authenticated birth certificate')
        ->toContain('multiple')
        ->toContain('accept=".jpg,.jpeg,.png,.webp,.pdf"')
        ->toContain('up to 5 files each')
        ->not->toContain('Optional')
        ->not->toMatch('/<input[^>]*requirementFiles[^>]*\srequired/');

    // A credential without requirements has no upload fields, and says so.
    expect(requirementBlock(Livewire::actingAs($this->owner)->test(Create::class)->html(), $this->org->free))
        ->not->toContain('data-requirement-uploads')
        ->toContain('has no documentary requirements to upload');
});

test('REQ-04-18 the request is submitted with the requirement files, each under its requirement', function () {
    $tor = $this->org->tor->id;

    submitRequestForm(torRequestForm()
        ->set("requirementFiles.{$tor}.0", [fakePdf('clearance.pdf'), UploadedFile::fake()->image('clearance-page-2.jpg')])
        ->set("requirementFiles.{$tor}.1", [fakePdf('birth-certificate.pdf')]))
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    $documents = CredentialRequest::sole()->requirementDocuments()->get();

    expect($documents)->toHaveCount(3)
        ->and($documents->pluck('requirement')->all())->toBe(['Completed clearance', 'Completed clearance', 'PSA-authenticated birth certificate'])
        ->and($documents->pluck('original_name')->all())->toBe(['clearance.pdf', 'clearance-page-2.jpg', 'birth-certificate.pdf'])
        ->and($documents->every(fn (RequestDocument $document) => $document->kind === DocumentKind::Requirement
            && $document->request_representative_id === null
            && $document->uploaded_by === $this->owner->id))->toBeTrue();

    foreach ($documents as $document) {
        Storage::disk('local')->assertExists($document->path);
    }
});

test('REQ-04-18 every requirement is optional', function () {
    submitRequestForm(torRequestForm())->assertHasNoErrors();

    expect(CredentialRequest::sole()->requirementDocuments()->count())->toBe(0);
});

test('REQ-04-18 the review summary counts the requirement files', function () {
    torRequestForm()->set("requirementFiles.{$this->org->tor->id}.1", [fakePdf(), fakePdf('second.pdf')])
        ->call('review')->assertHasNoErrors()
        ->assertSee('Requirement files')->assertSee('2 files');
});

test('REQ-04-18 files are checked as soon as they are chosen: at most 5, JPG, PNG, WebP or PDF, 5 MB each', function (array $files, string $message) {
    $field = "requirementFiles.{$this->org->tor->id}.0";

    torRequestForm()->set($field, $files)->assertHasErrors([$field])->assertSee($message);
})->with([
    'six files' => [fn () => array_map(fn ($i) => fakePdf("page-{$i}.pdf"), range(1, 6)), 'Upload at most 5 files for “Completed clearance”.'],
    'a Word file' => [fn () => [UploadedFile::fake()->create('clearance.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')], 'must be a JPG, PNG, WebP or PDF file.'],
    'a 6 MB PDF' => [fn () => [fakePdf('big.pdf', 6 * 1024)], 'must be 5 MB or smaller.'],
]);

test('REQ-04-18 five files of 5 MB are accepted, and a bad file stops the review', function () {
    $field = "requirementFiles.{$this->org->tor->id}.0";

    torRequestForm()->set($field, array_map(fn ($i) => fakePdf("page-{$i}.pdf", 5 * 1024), range(1, 5)))
        ->assertHasNoErrors([$field])
        ->set("requirementFiles.{$this->org->tor->id}.1", [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
        ->call('review')
        ->assertHasErrors(["requirementFiles.{$this->org->tor->id}.1"])
        ->assertSet('reviewing', false);
});

test('REQ-04-18 removing a requirement\'s files clears them', function () {
    $field = "requirementFiles.{$this->org->tor->id}.0";

    submitRequestForm(torRequestForm()->set($field, [fakePdf()])->call('clearRequirementFiles', $this->org->tor->id, 0))->assertHasNoErrors();

    expect(CredentialRequest::sole()->requirementDocuments()->count())->toBe(0);
});

test('REQ-04-18 files chosen for another credential are not sent', function () {
    $other = CredentialType::factory()->withSteps([$this->org->registrarOffice])->create(['requirements' => ['Affidavit of loss']]);

    submitRequestForm(torRequestForm()->set("requirementFiles.{$other->id}.0", [fakePdf('affidavit.pdf')]))->assertHasNoErrors();

    expect(RequestDocument::count())->toBe(0);
});

// ── REQ-04-19: stored privately, shown to the owner and the Registrar ─────────────────────

function requestWithRequirementFiles(): CredentialRequest
{
    $request = submitRequest(test()->owner, test()->org->tor);
    workflow()->attachRequirementDocuments($request, test()->owner, ['Completed clearance' => [fakePdf('clearance.pdf')]]);

    return $request->fresh();
}

test('REQ-04-19 the evaluator sees each requirement with its files under Academic Record Verification', function () {
    $request = requestWithRequirementFiles();
    workflow()->startEvaluation($request, $this->registrar);
    $document = $request->requirementDocuments()->sole();

    $html = Livewire::actingAs($this->registrar)->test(StageShow::class, ['credentialRequest' => $request->fresh()])->html();

    expect(Str::after($html, 'data-check="academic_record"'))->toContain('data-requirement-documents')
        ->toContain('Completed clearance')
        ->toContain('clearance.pdf')
        ->toContain(route('request-documents.show', $document))
        ->toContain('PSA-authenticated birth certificate')
        ->toContain('No file uploaded');
});

test('REQ-04-19 the Records page and the owner\'s page list the requirement files', function () {
    $request = requestWithRequirementFiles();
    $url = route('request-documents.show', $request->requirementDocuments()->sole());

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSee('Documentary requirements')->assertSee('clearance.pdf')->assertSee($url, false);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertSee('Documentary requirements')->assertSee('clearance.pdf')->assertSee($url, false);
});

test('REQ-04-19 only the owner, registrars and admins can open a requirement file', function () {
    $url = route('request-documents.show', requestWithRequirementFiles()->requirementDocuments()->sole());

    $this->actingAs($this->owner)->get($url)->assertOk();
    $this->actingAs($this->registrar)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(requester($this->org->ccs))->get($url)->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff($this->org->ccs)->create())->get($url)->assertNotFound();
});

test('REQ-04-19 the workflow takes files only from the owner, for listed requirements, at most 5 each, at submission', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    expect(fn () => workflow()->attachRequirementDocuments($request, requester($this->org->ccs), ['Completed clearance' => [fakePdf()]]))
        ->toThrow(InvalidTransition::class)
        ->and(fn () => workflow()->attachRequirementDocuments($request, $this->owner, ['Diploma' => [fakePdf()]]))
        ->toThrow(InvalidTransition::class)
        ->and(fn () => workflow()->attachRequirementDocuments($request, $this->owner, ['Completed clearance' => array_map(fn ($i) => fakePdf("{$i}.pdf"), range(1, 6))]))
        ->toThrow(InvalidTransition::class);

    workflow()->startEvaluation($request, $this->registrar);

    expect(fn () => workflow()->attachRequirementDocuments($request->fresh(), $this->owner, ['Completed clearance' => [fakePdf()]]))
        ->toThrow(InvalidTransition::class);

    expect(RequestDocument::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('request-documents'))->toBe([]);
});
