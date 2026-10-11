<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-primary-50 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 text-center" wire:navigate>
                    <img src="{{ asset('images/uphsl-logo.webp') }}" alt="University of Perpetual Help System" class="h-20 w-auto" width="60" height="80">
                    <span class="text-sm font-semibold tracking-wide text-primary uppercase">{{ config('app.name') }}</span>
                </a>
                <div class="flex flex-col gap-6 rounded-xl border border-secondary/50 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
