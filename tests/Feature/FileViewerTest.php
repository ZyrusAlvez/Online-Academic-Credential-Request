<?php

use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Profile;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

/**
 * A request with a proof of payment waiting for verification and a representative with an SPA.
 */
function requestWithFiles(): CredentialRequest
{
    $request = evaluate(submitRequest(test()->owner, test()->org->tor), test()->registrar);
    uploadPayment($request);
    workflow()->declareRepresentative($request, test()->owner, ['full_name' => 'Pedro Santos'],
        UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'));

    return $request->fresh();
}

function storedId(User $owner): IdentityDocument
{
    Storage::disk(RequestWorkflow::proofDisk())->put("identity-documents/{$owner->id}/id.jpg", 'image');

    return IdentityDocument::factory()->for($owner)->create([
        'id_type' => 'Passport', 'path' => "identity-documents/{$owner->id}/id.jpg",
        'original_name' => 'passport.jpg', 'mime_type' => 'image/jpeg',
    ]);
}

test('REQ-09-14 the app layout carries the file viewer, with download, new tab and close but no zoom', function () {
    $this->actingAs($this->owner)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('data-file-viewer')
        ->assertSeeHtml('x-on:open-file.window="open($event.detail)"')
        ->assertDontSee('Zoom to full size')
        ->assertDontSeeHtml('zoomed')
        ->assertSee('Download')
        ->assertSee('Open in new tab')
        ->assertSee('Close')
        ->assertSeeHtml("+ 'download=1'");
});

test('REQ-09-14 the viewer shows a loading indicator until the file loads, and a message if it fails', function () {
    $html = $this->actingAs($this->owner)->get(route('settings.profile'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-file-loading')
        ->toContain('Loading…')
        ->toContain('x-show="loading"')
        ->toContain('this.loading = this.isImage || this.isPdf;')
        ->toContain('x-on:load="loading = false"')
        ->toContain('x-on:error="loading = false; failed = true"')
        ->toContain('if ($el.complete && $el.naturalWidth) loading = false')
        ->toContain('This file couldn&#039;t be loaded.');
});

test('REQ-09-16 an image on a page shows a placeholder until it loads', function () {
    $request = requestWithFiles();
    $proof = $request->paymentProofs()->sole();

    $html = Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])->html();

    expect($html)->toMatch('/data-loading-image.*?x-show="! loaded".*?animate-pulse.*?Loading image….*?<img src="'.preg_quote(e(route('payment-proofs.show', $proof)), '/').'"/s')
        ->and($html)->toContain('x-init="if ($el.complete) loaded = true"')
        ->and($html)->toContain('x-on:load="loaded = true"');
});

test('REQ-09-14 every uploaded file opens in the viewer and stays a real link to the file', function () {
    $request = requestWithFiles();
    $id = $request->identityDocument;   // the ID sent with the request, also the saved ID (spec 15)
    $id->update(['mime_type' => 'image/jpeg']);
    $proof = $request->paymentProofs()->sole();
    $spa = $request->documents()->sole();

    $requestPage = Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])->html();
    $ownRequestPage = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])->html();
    $profile = Livewire::actingAs($this->owner)->test(Profile::class)->html();

    $links = [
        [$requestPage, route('identity-documents.show', $id), 'image/jpeg'],
        [$requestPage, route('payment-proofs.show', $proof), $proof->mime_type],
        [$requestPage, route('request-documents.show', $spa), 'application/pdf'],
        [$ownRequestPage, route('identity-documents.show', $id), 'image/jpeg'],
        [$profile, route('identity-documents.show', $id), 'image/jpeg'],
    ];

    foreach ($links as [$html, $url, $type]) {
        expect($html)->toMatch('/<a\s+href="'.preg_quote(e($url), '/').'"\s+target="_blank"\s+rel="noopener"\s+aria-haspopup="dialog"\s+data-file-link/')
            ->and($html)->toContain("\$dispatch('open-file', { url: '".str_replace('/', '\/', $url)."'")
            ->and($html)->toContain("type: '".str_replace('/', '\/', $type)."'");
    }

    // Modified clicks are left to the browser, so they open a new tab.
    expect($profile)->toContain('if ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey || $event.button !== 0) return;');

    // The proof's thumbnail on the request page opens the viewer too.
    expect($requestPage)->toMatch('/data-file-link[^>]*>\s*<span[^>]*data-loading-image.*?<img src="'.preg_quote(e(route('payment-proofs.show', $proof)), '/').'"/s');
});

test('REQ-09-15 a file is served inline for the viewer, or as a download when asked', function () {
    $request = requestWithFiles();
    $files = [
        route('identity-documents.show', storedId($this->owner)),
        route('payment-proofs.show', $request->paymentProofs()->sole()),
        route('request-documents.show', $request->documents()->sole()),
    ];

    foreach ($files as $url) {
        $inline = $this->actingAs($this->registrar)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        expect($inline->headers->get('Content-Disposition'))->toStartWith('inline');

        $download = $this->actingAs($this->owner)->get($url.'?download=1')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($download->headers->get('Content-Disposition'))->toStartWith('attachment');

        // Asking for a download changes nothing about who may see the file.
        $this->actingAs(requester($this->org->cba))->get($url.'?download=1')->assertNotFound();
    }
});
