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
        ->toContain('No documentary requirements.');
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

test('REQ-04-18 the review summary lists every requirement with the names of its files', function () {
    $summary = Str::after(torRequestForm()->set("requirementFiles.{$this->org->tor->id}.1", [fakePdf('clearance.pdf', 200), fakePdf('second.pdf', 100)])
        ->call('review')->assertHasNoErrors()->html(), 'data-summary-title');

    expect($summary)->toContain('2. Documentary Requirements')
        ->and(Str::between($summary, 'Completed clearance', 'PSA-authenticated birth certificate'))->toContain('No files')
        ->and(Str::after($summary, 'PSA-authenticated birth certificate'))->toMatch('#clearance\.pdf</a>\s*\(200 KB\)#')->toMatch('#second\.pdf</a>\s*\(100 KB\)#');
});

test('REQ-15-17 each requirement file on the summary is a link that opens it in the file viewer', function () {
    $summary = Str::after(torRequestForm()->set("requirementFiles.{$this->org->tor->id}.1", [fakePdf('clearance.pdf', 200)])
        ->call('review')->assertHasNoErrors()->html(), '2. Documentary Requirements</h2>');

    expect($summary)->toMatch('#<a\s+href="[^"]*preview-file[^"]*"[^>]*aria-haspopup="dialog"[^>]*data-file-link[^>]*>clearance\.pdf</a>\s*\(200 KB\)#')
        ->and($summary)->toMatch('#application\\\\?/pdf#');
});

test('REQ-04-18 the file count stays right of the requirement and the button right of the field', function () {
    $field = Str::between(requirementBlock(torRequestForm()->html(), $this->org->tor), 'data-requirement-field="0"', 'data-requirement-field="1"');

    // The name takes the room left by the count, which never wraps under it.
    expect($field)->toMatch('/<h3 class="min-w-0 flex-1[^"]*">Completed clearance/')
        ->toMatch('/<span class="shrink-0 whitespace-nowrap[^"]*" data-file-count>/')
        ->toMatch('/<div x-show="! uploading" class="mt-3 flex justify-end">\s*<label/');
});

test('REQ-04-18 while files upload, the field shows a progress bar with the percentage in place of the button', function () {
    $field = Str::between(requirementBlock(torRequestForm()->html(), $this->org->tor), 'data-requirement-field="0"', 'data-requirement-field="1"');

    expect($field)->toContain('x-on:livewire-upload-start="uploading = true; progress = 0"')
        ->toContain('x-on:livewire-upload-progress="progress = $event.detail.progress"')
        ->toContain('x-on:livewire-upload-finish="uploading = false"')
        ->toContain('x-on:livewire-upload-error="uploading = false"')
        ->toMatch('/<div x-show="uploading"[^>]*role="status"[^>]*data-upload-progress>/')
        ->toContain('x-text="progress + \'%\'"')
        ->toContain('Uploading…')
        // Not the old loading text, which sat beside the button.
        ->not->toContain('wire:loading');
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

test('REQ-04-18 removing a requirement\'s only file clears it', function () {
    $field = "requirementFiles.{$this->org->tor->id}.0";

    submitRequestForm(torRequestForm()->set($field, [fakePdf()])->call('removeRequirementFile', $this->org->tor->id, 0, 0))->assertHasNoErrors();

    expect(CredentialRequest::sole()->requirementDocuments()->count())->toBe(0);
});

test('REQ-04-18 each field lists its chosen files by name and size, each with Remove, and counts them', function () {
    $tor = $this->org->tor->id;
    $field = "requirementFiles.{$tor}.0";
    $fieldHtml = fn (Testable $form): string => Str::between(requirementBlock($form->html(), $this->org->tor), 'data-requirement-field="0"', 'data-requirement-field="1"');

    $form = torRequestForm();
    expect($fieldHtml($form))->toContain('No files yet')->toContain('Choose files')
        ->not->toContain('data-chosen-files')
        // The browser's picker, with its "No file chosen" or last file's name, is hidden.
        ->toMatch('/<input type="file" wire:model="'.preg_quote($field).'" multiple[^>]*class="sr-only"/');

    $form->set($field, [fakePdf('clearance.pdf', 200), UploadedFile::fake()->image('clearance-page-2.jpg')]);
    expect($fieldHtml($form))->toContain('2 of 5 files')
        ->toContain('clearance.pdf')->toContain('200 KB')
        ->toContain('clearance-page-2.jpg')
        ->toContain('aria-label="Remove clearance.pdf"')
        ->toContain('Add more files');

    // Remove takes out that file only.
    $form->call('removeRequirementFile', $tor, 0, 0)->assertHasNoErrors();
    expect($fieldHtml($form))->toContain('1 of 5 files')->toContain('clearance-page-2.jpg')
        ->not->toContain('clearance.pdf');

    // Choosing files again adds to them; at five there is nothing more to add.
    $form->set($field, array_map(fn ($i) => fakePdf("page-{$i}.pdf"), range(1, 4)));
    expect($fieldHtml($form))->toContain('5 of 5 files')->toContain('clearance-page-2.jpg')
        ->not->toContain('type="file"');

    // Removing a file that broke the limit clears the error.
    torRequestForm()->set($field, array_map(fn ($i) => fakePdf("page-{$i}.pdf"), range(1, 6)))->assertHasErrors([$field])
        ->call('removeRequirementFile', $tor, 0, 5)->assertHasNoErrors([$field]);
});

test('REQ-04-18 each chosen file\'s name in its field is a link that opens it in the file viewer', function () {
    $tor = $this->org->tor->id;
    $field = Str::between(requirementBlock(torRequestForm()
        ->set("requirementFiles.{$tor}.0", [fakePdf('clearance.pdf'), UploadedFile::fake()->image('clearance-page-2.jpg')])
        ->html(), $this->org->tor), 'data-requirement-field="0"', 'data-requirement-field="1"');

    expect($field)->toMatch('#<a\s+href="[^"]*preview-file[^"]*"[^>]*aria-haspopup="dialog"[^>]*data-file-link[^>]*>clearance\.pdf</a>#')
        ->toMatch('#<a\s+href="[^"]*preview-file[^"]*"[^>]*data-file-link[^>]*>clearance-page-2\.jpg</a>#')
        ->toMatch('#application\\\\?/pdf#');
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

test('REQ-04-19 the evaluator sees each requirement with its files under Credential Request Verification', function () {
    $request = requestWithRequirementFiles();
    workflow()->startEvaluation($request, $this->registrar);
    $document = $request->requirementDocuments()->sole();

    $html = Livewire::actingAs($this->registrar)->test(StageShow::class, ['credentialRequest' => $request->fresh()])->html();

    $card = Str::between($html, 'data-check="credential_request"', 'data-check="identity"');

    expect(Str::after($html, 'data-check="academic_record"'))->not->toContain('data-requirement-document')
        ->and($card)->toContain('data-requirement-document')
        ->toContain(route('request-documents.show', $document))
        // Each requirement is a row: its name left, its files (or a grey "No file uploaded") right (REQ-09-22).
        ->toMatch('#<dt class="text-zinc-600">Completed clearance</dt>\s*<dd[^>]*>.*?clearance\.pdf#s')
        ->toMatch('#<dt class="text-zinc-600">PSA-authenticated birth certificate</dt>\s*<dd[^>]*>\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<span class="font-normal text-zinc-600 italic" data-empty-value>No file uploaded</span>#');
});

test('REQ-04-19 the Records page and the owner\'s page list the requirement files', function () {
    $request = requestWithRequirementFiles();
    $url = route('request-documents.show', $request->requirementDocuments()->sole());

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $request])
        ->assertSeeHtml('data-requirement-document')->assertSee('Completed clearance')->assertSee('clearance.pdf')->assertSee($url, false);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertSeeHtml('data-requirement-document')->assertSee('Completed clearance')->assertSee('clearance.pdf')->assertSee($url, false);
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
