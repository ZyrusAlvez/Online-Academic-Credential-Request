<?php

namespace App\Livewire\Student;

use App\Models\CredentialRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My Requests')]
class Dashboard extends Component
{
    use WithPagination;

    public function render(): View
    {
        $requests = CredentialRequest::query()
            ->whereBelongsTo(Auth::user(), 'student')
            ->with(['credentialType:id,name', 'currentStep.department:id,name'])
            ->latest('id')
            ->paginate(10);

        return view('livewire.student.dashboard', ['requests' => $requests]);
    }
}
