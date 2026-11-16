<?php

namespace App\Livewire\Cashier;

use App\Enums\RegistrarQueue;
use App\Enums\RequestChannel;
use App\Enums\Role;
use App\Queries\StageQueue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The cashier's Payment Validation: the online requests whose proof awaits verification, and
 * those still awaiting payment apart (REQ-25-04).
 */
#[Title('Payment Validation')]
class Payments extends Component
{
    use WithPagination;

    #[Url(except: false)]
    public bool $waiting = false;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Cashier), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['waiting', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $queue = RegistrarQueue::Payment;

        // The registrar's queue list, with the cashier's requests and pages (REQ-25-04).
        return view('livewire.registrar.queue', [
            'queue' => $queue,
            'description' => __('Online payments to validate.'),
            'requests' => StageQueue::for($queue, $this->waiting, '', $this->search, RequestChannel::Online)->paginate(20),
            'hasWaitingTab' => true,
            'showWaiting' => $this->waiting,
            'waitingCount' => StageQueue::for($queue, true, channel: RequestChannel::Online)->count(),
            'offices' => collect(),
            'linkRoute' => 'cashier.payments.show',
        ]);
    }
}
