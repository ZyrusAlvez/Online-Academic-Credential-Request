<?php

use App\Livewire\Forms\RequesterProfileForm;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Create an account')] class extends Component {
    use \App\Livewire\Concerns\TrimsInput;

    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $contact_number = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $privacy_consent = false;

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'contact_number' => ['required', 'string', 'max:20', RequesterProfileForm::PHONE_RULE],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'privacy_consent' => ['accepted'],
        ], [
            'contact_number.regex' => RequesterProfileForm::PHONE_MESSAGE,
            'privacy_consent.accepted' => 'You must agree to the processing of your personal data to create an account.',
        ]);
        unset($validated['privacy_consent']);

        $validated['password'] = Hash::make($validated['password']);
        $validated['middle_name'] = filled($validated['middle_name']) ? $validated['middle_name'] : null;

        // Self-registered accounts are always students; role is not mass assignable.
        $user = new User($validated);
        $user->forceFill(['privacy_consented_at' => now()])->save();
        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Create an account" description="Enter your details below to create your account" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="first_name" id="first_name" label="{{ __('First name') }}" type="text" name="first_name" required autofocus autocomplete="given-name" />
            <flux:input wire:model="last_name" id="last_name" label="{{ __('Last name') }}" type="text" name="last_name" required autocomplete="family-name" />
        </div>

        <flux:input wire:model="middle_name" id="middle_name" label="{{ __('Middle name') }}" badge="Optional" type="text" name="middle_name" autocomplete="additional-name" />

        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
        </div>

        <flux:input wire:model="contact_number" id="contact_number" label="{{ __('Contact number') }}" type="tel" name="contact_number" required autocomplete="tel" inputmode="numeric" maxlength="11" placeholder="09171234567" />

        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                :description="__('At least 8 characters.')"
                viewable
            />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                viewable
            />
        </div>

        <flux:checkbox wire:model="privacy_consent" id="privacy_consent" name="privacy_consent"
            :label="__('I agree to the processing of my personal data')"
            :description="__('The University of Perpetual Help System Laguna uses your details, academic information and the valid ID you upload only to process your credential requests, in accordance with the Data Privacy Act of 2012 (RA 10173).')" />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600">
        Already have an account?
        <x-text-link href="{{ route('login') }}">Log in</x-text-link>
    </div>
</div>
