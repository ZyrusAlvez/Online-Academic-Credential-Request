<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\StepStatus;
use App\Events\RequestEventRecorded;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\PaymentProof;
use App\Models\RequestEvent;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single place where credential requests and their steps change status.
 *
 * Every public method runs in a transaction, locks the request row, checks that the
 * transition is allowed from the current status, and appends a ledger entry
 * (request_events). Listeners are notified after commit via RequestEventRecorded.
 */
class RequestWorkflow
{
    public const PROOF_DISK = 'local';

    /**
     * @param  array{credential_type_id: int, college_id: int, student_number: string, program: string, student_status: string, last_school_year: string, purpose: string, copies: int}  $data
     */
    public function submit(User $student, array $data): CredentialRequest
    {
        return DB::transaction(function () use ($student, $data): CredentialRequest {
            $type = CredentialType::requestable()->findOrFail($data['credential_type_id']);

            $request = new CredentialRequest($data);
            $request->forceFill([
                'user_id' => $student->id,
                'requires_payment' => $type->requires_payment,
                'fee' => $type->fee,
                'status' => $type->requires_payment ? RequestStatus::AwaitingPayment : RequestStatus::RegistrarReview,
            ])->save();

            $request->forceFill([
                'reference_no' => sprintf('CR-%s-%06d', $request->created_at->format('Y'), $request->id),
            ])->save();

            $this->record($request, $student, RequestEventType::Submitted, null, $request->status);

            return $request;
        });
    }

    public function submitPaymentProof(
        CredentialRequest $request,
        User $student,
        UploadedFile $file,
        ?string $referenceNo = null,
        ?string $amount = null,
    ): PaymentProof {
        $path = $file->storeAs(
            "payment-proofs/{$request->id}",
            Str::uuid().'.'.$file->extension(),
            self::PROOF_DISK,
        );

        try {
            return DB::transaction(function () use ($request, $student, $file, $path, $referenceNo, $amount): PaymentProof {
                $locked = $this->lock($request, [RequestStatus::AwaitingPayment], 'A receipt can only be uploaded while the request is awaiting payment.');

                $proof = $locked->paymentProofs()->create([
                    'uploaded_by' => $student->id,
                    'path' => (string) $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'reference_no' => filled($referenceNo) ? $referenceNo : null,
                    'amount' => filled($amount) ? $amount : null,
                    'status' => PaymentStatus::Submitted,
                ]);

                $this->transition($locked, $request, RequestStatus::PaymentReview, $student, RequestEventType::PaymentSubmitted);

                return $proof;
            });
        } catch (Throwable $e) {
            Storage::disk(self::PROOF_DISK)->delete((string) $path);

            throw $e;
        }
    }

    public function rejectPayment(CredentialRequest $request, User $registrar, string $remarks): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PaymentReview], 'Only payments under review can be rejected.');

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Rejected, $remarks);

            $this->transition($locked, $request, RequestStatus::AwaitingPayment, $registrar, RequestEventType::PaymentRejected, $remarks);
        });
    }

    /**
     * Approve a reviewed request (accepting its payment if required) and route it to the
     * first office of a snapshot of the credential type's current workflow.
     */
    public function approve(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::PaymentReview, RequestStatus::RegistrarReview],
                'Only requests under review can be approved.',
            );

            $template = $locked->credentialType()->firstOrFail()->steps()->get();

            if ($template->isEmpty()) {
                throw new InvalidTransition('This credential type has no workflow steps. Add steps before approving.');
            }

            if ($locked->status === RequestStatus::PaymentReview) {
                $this->reviewLatestProof($locked, $registrar, PaymentStatus::Accepted);
                $this->record($locked, $registrar, RequestEventType::PaymentAccepted, $locked->status, null);
            }

            foreach ($template as $index => $step) {
                $locked->steps()->create([
                    'position' => $index + 1,
                    'department_id' => $step->is_student_college ? $locked->college_id : $step->department_id,
                    'instructions' => $step->instructions,
                    'status' => $index === 0 ? StepStatus::Pending : StepStatus::Waiting,
                ]);
            }

            $locked->routed_at = now();
            $this->transition($locked, $request, RequestStatus::InProgress, $registrar, RequestEventType::Routed, $remarks);
        });
    }

    public function changeCollege(CredentialRequest $request, User $registrar, Department $college): void
    {
        DB::transaction(function () use ($request, $registrar, $college): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::AwaitingPayment, RequestStatus::PaymentReview, RequestStatus::RegistrarReview],
                'The college can only be changed before the request is routed.',
            );

            $previous = $locked->college()->firstOrFail();

            if ($previous->is($college)) {
                return;
            }

            $locked->college_id = $college->id;
            $locked->save();
            $request->college_id = $college->id;
            $request->unsetRelation('college');

            $this->record($locked, $registrar, RequestEventType::CollegeChanged, null, null, "From {$previous->name} to {$college->name}.");
        });
    }

    /**
     * Students may cancel before routing; the registrar may cancel any open request.
     */
    public function cancel(CredentialRequest $request, User $actor, ?string $reason = null): void
    {
        $isRegistrar = $actor->hasRole(Role::Registrar);

        if ($isRegistrar && blank($reason)) {
            throw new InvalidTransition('A reason is required to cancel a request.');
        }

        DB::transaction(function () use ($request, $actor, $reason, $isRegistrar): void {
            $allowed = $isRegistrar
                ? RequestStatus::open()
                : [RequestStatus::AwaitingPayment, RequestStatus::PaymentReview, RequestStatus::RegistrarReview];

            $locked = $this->lock($request, $allowed, $isRegistrar
                ? 'This request is already closed.'
                : 'This request can no longer be cancelled because it is already being processed.');

            $locked->cancelled_at = now();
            $locked->cancel_reason = filled($reason) ? $reason : null;
            $this->transition($locked, $request, RequestStatus::Cancelled, $actor, RequestEventType::Cancelled, $reason);
        });
    }

    /**
     * Re-read the request with a row lock and assert its status.
     *
     * @param  list<RequestStatus>  $allowed
     */
    private function lock(CredentialRequest $request, array $allowed, string $message): CredentialRequest
    {
        /** @var CredentialRequest $locked */
        $locked = CredentialRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowed, true)) {
            throw new InvalidTransition($message);
        }

        return $locked;
    }

    /**
     * Save a status change on the locked row, mirror it on the caller's instance and log it.
     */
    private function transition(
        CredentialRequest $locked,
        CredentialRequest $original,
        RequestStatus $to,
        ?User $actor,
        RequestEventType $type,
        ?string $remarks = null,
        ?RequestStep $step = null,
    ): RequestEvent {
        $from = $locked->status;
        $locked->status = $to;
        $locked->save();

        $original->setRawAttributes($locked->getAttributes(), sync: true);

        return $this->record($locked, $actor, $type, $from, $to, $remarks, $step);
    }

    private function record(
        CredentialRequest $request,
        ?User $actor,
        RequestEventType $type,
        ?RequestStatus $from,
        ?RequestStatus $to,
        ?string $remarks = null,
        ?RequestStep $step = null,
    ): RequestEvent {
        $event = $request->events()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'request_step_id' => $step?->id,
            'department_id' => $step?->department_id,
            'remarks' => filled($remarks) ? $remarks : null,
        ]);

        DB::afterCommit(fn () => RequestEventRecorded::dispatch($event));

        return $event;
    }

    private function reviewLatestProof(CredentialRequest $request, User $registrar, PaymentStatus $status, ?string $remarks = null): void
    {
        $proof = $request->paymentProofs()->where('status', PaymentStatus::Submitted)->first();

        if ($proof === null) {
            throw new InvalidTransition('There is no proof of payment waiting for verification.');
        }

        $proof->forceFill([
            'status' => $status,
            'reviewed_by' => $registrar->id,
            'reviewed_at' => now(),
            'remarks' => filled($remarks) ? $remarks : null,
        ])->save();
    }
}
