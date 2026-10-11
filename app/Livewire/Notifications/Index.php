<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\OpensNotifications;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Index extends Component
{
    use OpensNotifications, WithPagination;

    public function render(): View
    {
        return view('livewire.notifications.index', [
            'notifications' => $this->user()->notifications()->paginate(20),
            'unread' => $this->user()->unreadNotifications()->count(),
        ]);
    }
}
