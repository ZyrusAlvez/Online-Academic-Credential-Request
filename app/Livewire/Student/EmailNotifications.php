<?php

namespace App\Livewire\Student;

use App\Actions\Account\SetEmailNotifications;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → Email notifications (REQ-06-16): one switch, saved as soon as it is flipped.
 */
#[Title('Email notifications')]
class EmailNotifications extends Component
{
    public bool $enabled = true;

    public function mount(): void
    {
        $this->enabled = $this->user()->wantsEmailNotifications();
    }

    public function updatedEnabled(SetEmailNotifications $setEmailNotifications): void
    {
        $setEmailNotifications->handle($this->user(), $this->enabled);

        $this->dispatch('notify', message: $this->enabled ? __('Email notifications turned on.') : __('Email notifications turned off.'));
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function render(): View
    {
        return view('livewire.student.email-notifications');
    }
}
