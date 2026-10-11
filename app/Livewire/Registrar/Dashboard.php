<?php

namespace App\Livewire\Registrar;

use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Queries\StepQueue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Registrar Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);
    }

    public function render(): View
    {
        $office = Department::where('code', Department::REGISTRAR_CODE)->first();

        return view('livewire.registrar.dashboard', [
            'office' => $office,
            'officeQueue' => $office
                ? StepQueue::waiting($office->id)->get()->concat(StepQueue::waiting($office->id, StepStatus::OnHold)->get())
                : collect(),
            'reviewCount' => CredentialRequest::whereIn('status', [RequestStatus::PaymentReview, RequestStatus::RegistrarReview])->count(),
            'readyCount' => CredentialRequest::where('status', RequestStatus::ReadyForRelease)->count(),
        ]);
    }
}
