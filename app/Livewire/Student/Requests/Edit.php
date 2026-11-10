<?php

namespace App\Livewire\Student\Requests;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\ReviewStatus;
use App\Livewire\Forms\CredentialRequestForm;
use App\Models\CredentialRequest;
use App\Models\Purpose;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Edit Request (REQ-20-05..07): the New Request form for a request returned for clarification,
 * filled in from it, ending in Resubmit Request instead of a new submission. Representatives are
 * added on the request page, so the form's "Who will transact?" section is not shown.
 */
class Edit extends Create
{
    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $note = '';

    public function mount(?CredentialRequest $credentialRequest = null): void
    {
        abort_if($credentialRequest === null, 404);
        $this->authorize('edit', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
        $this->profile->load($this->owner());

        $purposeListed = Purpose::active()->where('name', $credentialRequest->purpose)->exists();
        $this->form->credential_type_id = $credentialRequest->credential_type_id;
        $this->form->purpose = $purposeListed ? $credentialRequest->purpose : CredentialRequestForm::OTHER_PURPOSE;
        $this->form->purpose_other = $purposeListed ? '' : $credentialRequest->purpose;
        $this->form->receiving_institution = (string) $credentialRequest->receiving_institution;
    }

    /**
     * Save the details, store a new ID and requirement copies, and resubmit, all or nothing
     * (REQ-20-07).
     */
    public function submit(RequestWorkflow $workflow, SubmitIdentityDocument $submitIdentity): void
    {
        if (! $this->reviewing) {
            $this->review();

            return;
        }

        $this->authorizeForm();

        $owner = $this->owner();
        $request = $this->credentialRequest;
        $stored = null;
        $requirementDocuments = [];

        try {
            $this->validate(['note' => ['nullable', 'string', 'max:1000']]);
            $changes = $this->form->validatedRequest();
            $this->validateRequirementFiles();
            $this->validateNewId();
            $requirementFiles = $this->chosenRequirementFiles();

            DB::transaction(function () use ($workflow, $submitIdentity, $owner, $request, $changes, $requirementFiles, &$stored, &$requirementDocuments): void {
                $chosen = $this->profile->save($owner);

                if ($chosen !== null) {
                    $stored = $submitIdentity->handle($owner, $chosen['type'], $chosen['file']);
                }

                $workflow->resubmit($request, $owner, $this->note, $changes, $stored, $requirementFiles !== []);

                if ($requirementFiles !== []) {
                    // The credential may have changed: its requirement list is read afresh.
                    $requirementDocuments = $workflow->attachRequirementDocuments($request->refresh(), $owner, $requirementFiles);
                }
            });
        } catch (Throwable $e) {
            $this->undo($owner, $stored, $requirementDocuments);
            $this->showFailure($e);

            return;
        }

        session()->flash('notify', "Request {$request->reference_no} resubmitted. The Office of the Registrar will evaluate it again.");
        $this->redirectRoute('student.requests.show', $request, navigate: true);
    }

    public function render(): View
    {
        return parent::render()->with([
            'editing' => $this->credentialRequest,
            'idRejected' => $this->idRejected(),
            // A rejected ID is not offered again: another is required (REQ-20-06).
            'savedId' => $this->idRejected() ? null : $this->owner()->latestIdentityDocument()->first(),
        ])->title(__('Edit Request :ref', ['ref' => $this->credentialRequest->reference_no]));
    }

    protected function authorizeForm(): void
    {
        $this->authorize('edit', $this->credentialRequest);
    }

    protected function extraChecks(): array
    {
        return [fn () => $this->validateNewId()];
    }

    /**
     * How the requester transacts is not part of this form, so its summary has no section 6.
     *
     * @return array<string, string>
     */
    protected function transactSummary(): array
    {
        return [];
    }

    /**
     * Files sent with the request before are kept; the summary lists only new ones.
     */
    protected function noRequirementFiles(): string
    {
        return __('No new files');
    }

    private function idRejected(): bool
    {
        return $this->credentialRequest->identity_status === ReviewStatus::Rejected;
    }

    /**
     * The ID sent with the request was not accepted: another one is required (REQ-20-06).
     *
     * @throws ValidationException
     */
    private function validateNewId(): void
    {
        if ($this->idRejected() && ($this->profile->id_file === null || blank($this->profile->id_type))) {
            throw ValidationException::withMessages([
                'profile.id_file' => 'Upload another valid ID: the one sent with this request was not accepted.',
            ]);
        }
    }
}
