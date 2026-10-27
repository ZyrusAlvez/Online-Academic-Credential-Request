<?php

use App\Actions\Auth\ConfirmVerificationCode;
use App\Actions\Auth\SendVerificationCode;
use App\Livewire\Actions\Logout;
use App\Models\EmailVerificationCode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Verify email')] class extends Component {
    use \App\Livewire\Concerns\TrimsInput;

    public string $code = '';

    /** Seconds until another code may be requested (REQ-01-23). */
    public int $cooldown = 0;

    /**
     * Send a code when the user has none that still works, e.g. after changing their email.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->cooldown = SendVerificationCode::cooldown($user);

        $hasLiveCode = EmailVerificationCode::where('user_id', $user->id)->where('expires_at', '>', now())->exists();

        if (! $user->hasVerifiedEmail() && ! $hasLiveCode && $this->cooldown === 0) {
            $this->resend(app(SendVerificationCode::class));
        }
    }

    public function verify(ConfirmVerificationCode $confirm): void
    {
        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            $this->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Enter the 6-digit code from the email.']);
            $confirm->handle($user, $this->code);
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function resend(SendVerificationCode $send): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        $this->resetErrorBag();
        $send->handle($user);

        $this->code = '';
        $this->cooldown = SendVerificationCode::COOLDOWN_SECONDS;
        $this->dispatch('code-sent', seconds: $this->cooldown);
        $this->dispatch('notify', message: 'We sent a new code to '.$user->email.'.');
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
    <x-auth-header title="Verify your email" :description="__('We sent a 6-digit code to :email. Enter it below to finish setting up your account.', ['email' => auth()->user()->email])" />

    <form wire:submit="verify" class="flex flex-col gap-6">
        <flux:input
            wire:model="code"
            id="code"
            name="code"
            :label="__('Verification code')"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            placeholder="123456"
            required
            autofocus
        />

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Verify email') }}</flux:button>
    </form>

    <div
        class="flex flex-col items-center gap-3"
        x-data="{ left: {{ $cooldown }}, timer: null }"
        x-init="timer = setInterval(() => { if (left > 0) left-- }, 1000)"
        x-on:code-sent.window="left = $event.detail.seconds"
    >
        <flux:text size="sm">{{ __('Didn\'t get it? Check your spam folder, or ask for a new code.') }}</flux:text>
        <flux:button wire:click="resend" variant="ghost" size="sm" x-bind:disabled="left > 0">
            <span x-show="left === 0">{{ __('Send a new code') }}</span>
            <span x-show="left > 0" x-cloak>{{ __('Send a new code in') }} <span x-text="left"></span>s</span>
        </flux:button>

        <flux:button wire:click="logout" variant="ghost" size="sm">
            {{ __('Log out') }}
        </flux:button>
    </div>
</div>
