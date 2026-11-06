<?php

namespace App\Livewire\Student\Requests;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\CredentialRequest;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The requester's view of one request: tracker, what is needed from them, payment,
 * representatives and history (specs 04, 06, 11).
 */
class Show extends Component
{
    use TrimsInput;
    use WithFileUploads;

    #[Locked]
    public CredentialRequest $credentialRequest;

    /** @var UploadedFile|null */
    public $receipt = null;

    public string $paymentReference = '';

    public string $amountPaid = '';

    public string $paidOn = '';

    public string $note = '';

    public string $cancelReason = '';

    public string $representativeName = '';

    public string $representativeRelationship = '';

    public string $representativeContact = '';

    /** @var UploadedFile|null */
    public $spa = null;

    /** @var UploadedFile|null */
    public $representativeId = null;

    public string $newIdType = '';

    /** @var UploadedFile|null */
    public $newIdFile = null;

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('view', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
    }

    public function uploadReceipt(RequestWorkflow $workflow): void
    {
        $this->authorize('uploadPayment', $this->credentialRequest);

        $this->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'paymentReference' => ['required', 'string', 'max:100'],
            'amountPaid' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'paidOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], attributes: ['receipt' => 'proof of payment', 'paymentReference' => 'payment reference no.', 'amountPaid' => 'amount paid', 'paidOn' => 'date paid']);

        try {
            $workflow->submitPaymentProof($this->credentialRequest, $this->owner(), $this->receipt, $this->paymentReference, $this->amountPaid, $this->paidOn);
        } catch (InvalidTransition $e) {
            $this->addError('receipt', $e->getMessage());

            return;
        }

        $this->reset('receipt', 'paymentReference', 'amountPaid', 'paidOn');
        $this->dispatch('notify', message: 'Proof of payment uploaded. The Office of the Registrar will verify it against the payment record.');
    }

    /**
     * Send the returned request back for evaluation (REQ-16-15).
     */
    public function resubmit(RequestWorkflow $workflow): void
    {
        $this->authorize('resubmit', $this->credentialRequest);
        $this->validate(['note' => ['nullable', 'string', 'max:1000']], attributes: ['note' => 'note']);

        try {
            $workflow->resubmit($this->credentialRequest, $this->owner(), $this->note);
        } catch (InvalidTransition $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->reset('note');
        $this->dispatch('notify', message: 'Resubmitted. The Office of the Registrar will evaluate your request again.');
    }

    public function declareRepresentative(RequestWorkflow $workflow): void
    {
        $this->authorize('declareRepresentative', $this->credentialRequest);

        $this->validate([
            'representativeName' => ['required', 'string', 'max:150'],
            'representativeRelationship' => ['nullable', 'string', 'max:100'],
            'representativeContact' => ['nullable', 'string', 'max:20', RequesterProfileForm::PHONE_RULE],
            'spa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'representativeId' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], ['representativeContact.regex' => RequesterProfileForm::PHONE_MESSAGE], ['representativeName' => "representative's full name", 'spa' => 'SPA', 'representativeId' => "representative's ID"]);

        try {
            $workflow->declareRepresentative($this->credentialRequest, $this->owner(), [
                'full_name' => $this->representativeName,
                'relationship' => filled($this->representativeRelationship) ? $this->representativeRelationship : null,
                'contact_number' => filled($this->representativeContact) ? $this->representativeContact : null,
            ], $this->spa, $this->representativeId);
        } catch (InvalidTransition $e) {
            $this->addError('representativeName', $e->getMessage());

            return;
        }

        Flux::modal('declare-representative')->close();
        $this->reset('representativeName', 'representativeRelationship', 'representativeContact', 'spa', 'representativeId');
        $this->dispatch('notify', message: 'Representative added. The Office of the Registrar will verify their SPA and IDs.');
    }

    /**
     * Another valid ID for this request after a rejection; it also becomes the saved ID, all or
     * nothing (REQ-15-11).
     */
    public function replaceIdentity(SubmitIdentityDocument $submitIdentity, RequestWorkflow $workflow): void
    {
        $this->authorize('replaceIdentity', $this->credentialRequest);

        $this->validate([
            'newIdType' => ['required', Rule::in(IdentityDocument::ID_TYPES)],
            'newIdFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], attributes: ['newIdType' => 'type of ID', 'newIdFile' => 'valid ID']);

        $owner = $this->owner();
        $stored = null;

        try {
            DB::transaction(function () use ($submitIdentity, $workflow, $owner, &$stored): void {
                $stored = $submitIdentity->handle($owner, $this->newIdType, $this->newIdFile);
                $workflow->replaceIdentity($this->credentialRequest, $owner, $stored);
            });
        } catch (InvalidTransition|ValidationException $e) {
            if ($stored !== null) {
                Storage::disk(RequestWorkflow::proofDisk())->delete($stored->path);
            }

            $this->addError('newIdFile', $e instanceof ValidationException ? $e->validator->errors()->first() : $e->getMessage());

            return;
        }

        $this->reset('newIdType', 'newIdFile');
        $this->dispatch('notify', message: 'Valid ID uploaded. The Office of the Registrar will verify it.');
    }

    public function cancel(RequestWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->credentialRequest);

        $this->validate(['cancelReason' => ['nullable', 'string', 'max:500']]);

        try {
            $workflow->cancel($this->credentialRequest, $this->owner(), $this->cancelReason);
        } catch (InvalidTransition $e) {
            Flux::modal('cancel-request')->close();
            $this->dispatch('notify', message: $e->getMessage());

            return;
        }

        Flux::modal('cancel-request')->close();
        $this->dispatch('notify', message: 'Your request was cancelled.');
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'credentialType', 'college', 'identityDocument', 'openVisit.department', 'paymentProofs', 'deficiencies',
            'representatives.documents', 'release', 'steps.department', 'checks',
        ]);

        $events = $request->events()->with(['department', 'step'])->get()
            ->reject(fn ($event) => $event->type->isInternal())
            ->values();

        $results = $request->checkResults(CheckStage::Evaluation);

        return view('livewire.student.requests.show', [
            'request' => $request,
            'rejectedProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Rejected),
            // What the evaluator rejected and why (REQ-16-15).
            'rejectedParts' => array_values(array_filter(
                CheckItem::for(CheckStage::Evaluation, $request),
                fn (CheckItem $item) => ($results[$item->value] ?? null) === CheckResult::Rejected,
            )),
            'returnRemarks' => $events->last(fn ($event) => $event->type === RequestEventType::ComplianceRequested)?->remarks,
            'events' => $events,
            'idTypes' => IdentityDocument::ID_TYPES,
        ])->title($request->reference_no);
    }

    private function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
