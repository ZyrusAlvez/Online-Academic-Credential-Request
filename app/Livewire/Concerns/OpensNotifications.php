<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait OpensNotifications
{
    /**
     * Mark one of the user's notifications read and go to the request it is about.
     */
    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        // Only follow links into this application.
        if ($url !== '' && parse_url($url, PHP_URL_HOST) === request()->getHost()) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
