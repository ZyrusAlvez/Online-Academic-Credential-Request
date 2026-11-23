<?php

use App\Livewire\Student\Requests\Create;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

test('REQ-09-14 a file chosen on New Request downloads through its signed preview, unchanged', function () {
    // The SPA of a representative: on the summary its name opens the signed preview.
    $form = Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)
        ->set('form.purpose', 'Employment abroad')
        ->set('form.requester_mode', 'representative')
        ->set('form.representative_name', 'Pedro Santos')
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 200, 'application/pdf'))
        ->set('representativeId', UploadedFile::fake()->create('pedro-id.pdf', 100, 'application/pdf'));

    expect(Str::after($form->call('review')->assertHasNoErrors()->html(), 'data-summary-title'))
        ->toMatch("#\\\$dispatch\('open-file', \{ url: '([^']*preview-file[^']*)'[^}]*download: '\\1' \}\)#");
});
