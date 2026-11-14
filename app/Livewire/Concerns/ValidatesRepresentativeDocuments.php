<?php

namespace App\Livewire\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The SPA and the representative's valid ID that every representative needs (REQ-11-01): both
 * required, JPG/PNG/WebP/PDF, 5 MB at most, each checked as soon as it is chosen (REQ-11-09).
 * Used by New Request and the request page; the component also uses WithFileUploads.
 */
trait ValidatesRepresentativeDocuments
{
    /** @var UploadedFile|null */
    public $spa = null;

    /** @var UploadedFile|null */
    public $representativeId = null;

    public function updatedSpa(): void
    {
        $this->validateOnly('spa', $this->representativeDocumentRules(), $this->representativeDocumentMessages(), $this->representativeDocumentAttributes());
    }

    public function updatedRepresentativeId(): void
    {
        $this->validateOnly('representativeId', $this->representativeDocumentRules(), $this->representativeDocumentMessages(), $this->representativeDocumentAttributes());
    }

    /**
     * @return array<string, list<string>>
     */
    protected function representativeDocumentRules(): array
    {
        $file = ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

        return ['spa' => $file, 'representativeId' => $file];
    }

    /**
     * @return array<string, string>
     */
    protected function representativeDocumentMessages(): array
    {
        $messages = [];

        foreach (['spa', 'representativeId'] as $field) {
            $messages += [
                "{$field}.required" => 'Upload the :attribute.',
                "{$field}.file" => 'The :attribute must be a file.',
                "{$field}.uploaded" => 'The :attribute could not be uploaded. Use a JPG, PNG, WebP or PDF file of 5 MB or smaller.',
                "{$field}.mimes" => 'The :attribute must be a JPG, PNG, WebP or PDF file.',
                "{$field}.max" => 'The :attribute must be 5 MB or smaller.',
            ];
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    protected function representativeDocumentAttributes(): array
    {
        return ['spa' => 'SPA', 'representativeId' => "representative's valid ID"];
    }

    /**
     * Both files after validation passed, typed for the workflow.
     *
     * @return array{UploadedFile, UploadedFile}
     */
    protected function representativeDocuments(): array
    {
        if (! $this->spa instanceof UploadedFile || ! $this->representativeId instanceof UploadedFile) {
            throw ValidationException::withMessages(['spa' => 'Upload the SPA and the representative\'s valid ID.']);
        }

        return [$this->spa, $this->representativeId];
    }
}
