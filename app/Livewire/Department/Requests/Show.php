<?php

namespace App\Livewire\Department\Requests;

use App\Enums\DepartmentQueue;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\ActsOnVisits;
use App\Livewire\Concerns\ReturnsToList;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Support\DepartmentLink;
use App\Support\ReturnOffices;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A request as a department office sees it, under the office's list it is in: its details, the
 * verified proof of payment and the route, then the office's action (REQ-24-06, 07).
 */
class Show extends Component
{
    use ActsOnVisits {
        attempt as runTransition;
    }
    use ReturnsToList;
    use TrimsInput;

    #[Locked]
    public CredentialRequest $credentialRequest;

    #[Locked]
    public DepartmentQueue $currentQueue = DepartmentQueue::History;

    /**
     * @param  DepartmentQueue|string|null  $queue  the route's default; a page opened under another
     *                                              list than the request's moves there (REQ-24-06)
     */
    public function mount(CredentialRequest $credentialRequest, DepartmentQueue|string|null $queue = null): void
    {
        $this->authorize('view', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
        $this->currentQueue = DepartmentQueue::forRequest($credentialRequest, $this->departmentId());

        if ($queue !== null && $this->currentQueue !== (is_string($queue) ? DepartmentQueue::from($queue) : $queue)) {
            $this->redirect(DepartmentLink::to($credentialRequest, $this->departmentId()), navigate: true);

            return;
        }

        $this->rememberList($this->currentQueue->routeName());
    }

    /**
     * Run a transition; when it moves the request out of this list, go back to the list as the
     * user left it (REQ-24-06).
     */
    protected function attempt(Closure $transition, string $success): bool
    {
        if (! $this->runTransition($transition, $success)) {
            return false;
        }

        $request = $this->credentialRequest->fresh();

        if ($request === null || DepartmentQueue::forRequest($request, $this->departmentId()) !== $this->currentQueue) {
            $this->backToList($success);
        }

        return true;
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'credentialType', 'college', 'paymentProofs',
            'openVisit.department', 'openVisit.fromDepartment', 'openVisit.step', 'openVisit.credentialRequest.visits', 'openVisit.credentialRequest.steps.department',
            'steps.department',
        ]);

        return view('livewire.department.requests.show', [
            'request' => $request,
            'queue' => $this->currentQueue,
            // Only the verified proof (REQ-24-07).
            'proofs' => $request->paymentProofs->where('status', PaymentStatus::Verified)->values(),
            'returnOffices' => ReturnOffices::for($request),
        ])->title($request->reference_no);
    }

    private function departmentId(): int
    {
        return (int) $this->actor()->department_id;
    }
}
