<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Welcome'])
    </head>
    <body class="min-h-screen bg-white antialiased">
        <header class="bg-primary text-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/uphsl-logo.webp') }}" alt="" class="h-14 w-auto shrink-0" width="42" height="56">
                    <span class="leading-tight">
                        <span class="block text-base font-semibold">
                            <abbr title="University of Perpetual Help System" class="no-underline sm:hidden">UPHSL</abbr>
                            <span class="max-sm:hidden">University of Perpetual Help System</span>
                        </span>
                        <span class="block text-xs text-secondary">Office of the Registrar</span>
                    </span>
                </a>
                <nav aria-label="Account" class="flex shrink-0 items-center gap-2 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-4 py-2 font-medium text-primary hover:bg-primary-50">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 font-medium hover:bg-white/10">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-md bg-white px-4 py-2 font-medium text-primary hover:bg-primary-50">Register</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            <section class="bg-primary-50">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                    <p class="text-sm font-semibold tracking-wide text-highlight uppercase">Online Academic Credential Request</p>
                    <h1 class="mt-3 max-w-3xl text-3xl font-bold text-primary sm:text-5xl">Request your credentials and know exactly where they are.</h1>
                    <p class="mt-5 max-w-2xl text-lg text-ink">
                        Request your Transcript of Records, certifications and other academic documents online,
                        then follow each signature from office to office until it is ready for release.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-md bg-primary px-5 py-3 font-semibold text-white hover:bg-primary-700">Create an account</a>
                        <a href="{{ route('login') }}" class="rounded-md border border-primary px-5 py-3 font-semibold text-primary hover:bg-white">I already have an account</a>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6" aria-labelledby="how-it-works">
                <h2 id="how-it-works" class="text-2xl font-bold text-primary">How it works</h2>
                <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Request', 'Choose the credential you need and tell us your college and purpose.'],
                        ['Pay if required', 'For paid credentials such as the TOR, pay online and upload your receipt.'],
                        ['Track', 'See which office has your document and when each one signs it.'],
                        ['Claim', 'Get notified when it is ready for release at the Registrar.'],
                    ] as $i => [$step, $text])
                        <li class="rounded-xl border border-secondary/50 p-5">
                            <span class="flex size-9 items-center justify-center rounded-full bg-primary font-semibold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                            <h3 class="mt-4 font-semibold text-primary">{{ $step }}</h3>
                            <p class="mt-1 text-sm">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </main>

        <footer class="border-t border-secondary/40">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm sm:px-6">
                &copy; {{ now()->year }} University of Perpetual Help System &middot; Office of the Registrar
            </div>
        </footer>
    </body>
</html>
