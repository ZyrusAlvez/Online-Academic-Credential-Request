<?php

namespace App\Livewire\Student\Requests;

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Concerns\ValidatesRepresentativeDocuments;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
    use ValidatesRepresentativeDocuments;
    use WithFileUploads;

    #[Locked]
    public CredentialRequest $credentialRequest;

    /** @var UploadedFile|null */
    public $receipt = null;

    public string $paymentReference = '';

    public string $amountPaid = '';

    public string $paidOn = '';

    public string $cancelReason = '';

    public string $representativeName = '';

    public string $representativeRelationship = '';

    public string $representativeContact = '';

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

    public function declareRepresentative(RequestWorkflow $workflow): void
    {
        $this->authorize('declareRepresentative', $this->credentialRequest);

        // Every field at once, so all that needs attention shows together (REQ-11-01, 09).
        $this->validate([
            'representativeName' => ['required', 'string', 'max:150'],
            'representativeRelationship' => ['nullable', 'string', 'max:100'],
            'representativeContact' => ['nullable', 'string', 'max:20', RequesterProfileForm::PHONE_RULE],
            ...$this->representativeDocumentRules(),
        ], [
            'representativeContact.regex' => RequesterProfileForm::PHONE_MESSAGE,
            ...$this->representativeDocumentMessages(),
        ], [
            'representativeName' => "representative's full name",
            ...$this->representativeDocumentAttributes(),
        ]);

        [$spa, $idFile] = $this->representativeDocuments();

        try {
            $workflow->declareRepresentative($this->credentialRequest, $this->owner(), [
                'full_name' => $this->representativeName,
                'relationship' => filled($this->representativeRelationship) ? $this->representativeRelationship : null,
                'contact_number' => filled($this->representativeContact) ? $this->representativeContact : null,
            ], $spa, $idFile);
        } catch (InvalidTransition $e) {
            $this->addError('representativeName', $e->getMessage());

            return;
        }

        Flux::modal('declare-representative')->close();
        $this->reset('representativeName', 'representativeRelationship', 'representativeContact', 'spa', 'representativeId');
        $this->dispatch('notify', message: 'Representative added. The Office of the Registrar will verify their SPA and IDs.');
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
            'credentialType', 'college', 'identityDocument', 'openVisit.department', 'openVisit.fromDepartment', 'paymentProofs', 'deficiencies',
            'representatives.documents', 'requirementDocuments', 'release', 'steps.department', 'checks',
        ]);

        $events = $request->events()->with(['department', 'step'])->get()
            ->reject(fn ($event) => $event->type->isInternal())
            ->values();

        $results = $request->checkResults(CheckStage::Evaluation);

        // The return's ledger line is "Rejected: …" or "Missing: …", then the remarks.
        $returnType = $request->status === RequestStatus::PendingCompliance ? RequestEventType::ComplianceRequested : RequestEventType::CustomerActionRequested;
        $returnLine = (string) $events->last(fn ($event) => $event->type === $returnType)?->remarks;

        return view('livewire.student.requests.show', [
            'request' => $request,
            'rejectedProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Rejected),
            // What the evaluator rejected and why (REQ-20-05).
            'rejectedParts' => array_values(array_filter(
                CheckItem::for(CheckStage::Evaluation, $request),
                fn (CheckItem $item) => ($results[$item->value] ?? null) === CheckResult::Rejected,
            )),
            'returnRemarks' => Str::contains($returnLine, "\n") ? Str::after($returnLine, "\n") : $returnLine,
            'events' => $events,
        ])->title($request->reference_no);
    }

    private function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
