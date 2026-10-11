<?php

namespace App\Livewire;

use App\Livewire\Concerns\OpensNotifications;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    use OpensNotifications;

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
