<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Verify email')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Verify your email" :description="__('We sent a verification link to :email. Click it to finish setting up your account.', ['email' => auth()->user()->email])" />

    @if (session('status') == 'verification-link-sent')
        <flux:callout icon="check-circle" color="green">
            <flux:callout.text>{{ __('A new verification link has been sent. Check your inbox and spam folder.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-col items-center gap-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">
            {{ __('Resend verification email') }}
        </flux:button>

        <flux:button wire:click="logout" variant="ghost" size="sm">
            {{ __('Log out') }}
        </flux:button>
    </div>
</div>
