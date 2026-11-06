<?php

use App\Livewire\Registrar\CredentialTypes\Index as CredentialTypesIndex;
use App\Livewire\Registrar\Departments\Index as DepartmentsIndex;
use App\Livewire\Registrar\Users\Index as UsersIndex;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

const VISIBILITY_TOGGLE = 'Toggle password visibility';

function toggleCount(string $html): int
{
    return substr_count($html, 'aria-label="'.VISIBILITY_TOGGLE.'"');
}

test('REQ-09-01 every password field on the sign-in pages has a show/hide toggle', function () {
    expect(toggleCount($this->get(route('login'))->getContent()))->toBe(1)
        ->and(toggleCount($this->get(route('register'))->getContent()))->toBe(1)
        ->and(toggleCount($this->get(route('password.reset', 'token'))->getContent()))->toBe(2);

    $this->actingAs(User::factory()->create());

    expect(toggleCount($this->get(route('password.confirm'))->getContent()))->toBe(1)
        ->and(toggleCount($this->get(route('settings.password'))->getContent()))->toBe(3);
});

test('REQ-09-01 the staff account password field has a show/hide toggle', function () {
    Livewire::actingAs(User::factory()->registrar()->create())->test(UsersIndex::class)
        ->assertSeeHtml('aria-label="'.VISIBILITY_TOGGLE.'"');
});

const PASSWORD_RULES = [
    'At least 8 characters', 'Has an uppercase letter (A-Z)', 'Has a lowercase letter (a-z)',
    'Has a number (0-9)', 'Has a special character (!@#$)',
];

test('REQ-09-02 new-password fields list the password rules as a live checklist', function () {
    $checklist = fn (string $html, string $model) => expect($html)
        ->toContain('data-password-checklist')
        ->toContain("\$wire.\$get('{$model}')")
        ->and(substr_count($html, 'data-password-checklist'))->toBe(1);

    foreach ([route('register'), route('password.reset', 'token')] as $url) {
        $response = $this->get($url)->assertSeeInOrder(PASSWORD_RULES);
        $checklist($response->getContent(), 'password');
    }

    $this->actingAs(User::factory()->create());
    $response = $this->get(route('settings.password'))->assertSeeInOrder(PASSWORD_RULES);
    $checklist($response->getContent(), 'password');

    $staffForm = Livewire::actingAs(User::factory()->registrar()->create())->test(UsersIndex::class)
        ->assertSeeInOrder(PASSWORD_RULES);
    $checklist($staffForm->html(), 'form.password');
});

test('REQ-09-02 each rule is ticked by the same test the server applies', function () {
    $html = (string) $this->blade('<x-password-checklist />');

    // One check per rule in Password::defaults(): min(8), mixedCase(), numbers(), symbols().
    expect($html)
        ->toContain('length: [...v].length >= 8')
        ->toContain('upper: /\p{Lu}/u.test(v)')
        ->toContain('lower: /\p{Ll}/u.test(v)')
        ->toContain('number: /\p{N}/u.test(v)')
        ->toContain('symbol: /\p{Z}|\p{S}|\p{P}/u.test(v)')
        ->and(Password::defaults()->appliedRules())->toMatchArray([
            'min' => 8, 'mixedCase' => true, 'numbers' => true, 'symbols' => true,
        ]);
});

test('REQ-09-03 password fields carry the right autocomplete hint', function () {
    $this->get(route('login'))->assertSee('autocomplete="current-password"', false);
    $this->get(route('register'))
        ->assertSee('autocomplete="new-password"', false)
        ->assertDontSee('autocomplete="current-password"', false);

    $this->actingAs(User::factory()->create());

    $this->get(route('password.confirm'))
        ->assertSee('autocomplete="current-password"', false)
        ->assertDontSee('autocomplete="new-password"', false);
    $this->get(route('settings.password'))
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('autocomplete="new-password"', false);
});

test('REQ-09-04 every page has its own browser tab title', function () {
    $title = fn (string $page) => '<title>'.$page.' · '.config('app.name').'</title>';

    $this->get(route('login'))->assertSee($title('Log in'), false);
    $this->get(route('register'))->assertSee($title('Create an account'), false);
    $this->get(route('password.request'))->assertSee($title('Forgot password'), false);
    $this->get(route('password.reset', 'token'))->assertSee($title('Reset password'), false);

    $this->actingAs(User::factory()->student()->create());

    $this->get(route('student.dashboard'))->assertSee($title('My Requests'), false);
    $this->get(route('settings.notifications'))->assertSee($title('Email notifications'), false);
    $this->get(route('settings.password'))->assertSee($title('Password settings'), false);
    $this->get(route('password.confirm'))->assertSee($title('Confirm password'), false);

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))->assertSee($title('Verify email'), false);
});

test('REQ-09-05 request pages offer a copy-tracking-number button', function () {
    Storage::fake('local');
    $org = organisation();
    $owner = requester($org->ccs);
    $registrar = User::factory()->registrar()->create();
    $request = acceptRequest(submitRequest($owner, $org->tor), $registrar);
    passOffice($request, $registrar);   // now forwarded to the Dean
    $copy = 'data-copy="'.$request->reference_no.'"';

    $this->actingAs($owner)->get(route('student.requests.show', $request))
        ->assertOk()->assertSee('Copy tracking number')->assertSee($copy, false);

    $this->actingAs($registrar)->get(route('registrar.requests.show', $request))
        ->assertOk()->assertSee('Copy tracking number')->assertSee($copy, false);

    $this->actingAs(User::factory()->departmentStaff($org->ccs)->create())
        ->get(route('department.requests.show', $request))
        ->assertOk()->assertSee('Copy tracking number')->assertSee($copy, false);
});

test('REQ-09-06 deactivating an account asks for confirmation; activating does not', function () {
    $registrar = User::factory()->registrar()->create();
    User::factory()->student()->create(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Active']);
    User::factory()->student()->inactive()->create(['first_name' => 'Ian', 'middle_name' => null, 'last_name' => 'Inactive']);

    Livewire::actingAs($registrar)->test(UsersIndex::class)
        ->assertSeeHtml('wire:confirm="Deactivate Ana Active?')
        ->assertDontSeeHtml('wire:confirm="Deactivate Ian Inactive?');
});

test('REQ-09-06 deactivating a department or credential type asks for confirmation', function () {
    $registrar = User::factory()->registrar()->create();
    Department::factory()->create(['name' => 'Library']);
    Department::factory()->inactive()->create(['name' => 'Old Office']);
    CredentialType::factory()->create(['name' => 'Diploma']);
    CredentialType::factory()->inactive()->create(['name' => 'Retired Form']);

    Livewire::actingAs($registrar)->test(DepartmentsIndex::class)
        ->assertSeeHtml('wire:confirm="Deactivate Library?')
        ->assertDontSeeHtml('wire:confirm="Deactivate Old Office?');

    Livewire::actingAs($registrar)->test(CredentialTypesIndex::class)
        ->assertSeeHtml('wire:confirm="Deactivate Diploma?')
        ->assertDontSeeHtml('wire:confirm="Deactivate Retired Form?');
});

test('REQ-09-13 app pages carry the in-app confirm dialog and label each confirmation', function () {
    Department::factory()->create(['name' => 'Library']);

    $this->actingAs(User::factory()->registrar()->create())
        ->get(route('registrar.departments'))
        ->assertOk()
        ->assertSee('data-confirm-dialog', false)
        ->assertSee('data-confirm-title="Deactivate department"', false)
        ->assertSee('data-confirm-label="Deactivate"', false)
        ->assertSee('data-confirm-variant="danger"', false)
        ->assertSee('wire:confirm="Deactivate Library?', false);
});

test('REQ-09-13 every confirmation goes through the app dialog, never the browser box', function () {
    $views = collect(File::allFiles(resource_path('views')))->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'));
    $sources = $views->map(fn ($file) => [$file->getRelativePathname(), $file->getContents()])
        ->push(['js/app.js', File::get(resource_path('js/app.js'))]);

    foreach ($sources as [$name, $source]) {
        expect($source)->not->toMatch('/(?<![\w.$])(?:window\.)?(confirm|alert|prompt)\s*\(/', "{$name} calls the browser's dialog")
            ->not->toContain('wire:confirm.prompt');

        foreach (explode("\n", $source) as $line) {
            if (preg_match('/\s:?wire:confirm=/', $line)) {
                expect(str_contains($line, 'data-confirm-title=') && str_contains($line, 'data-confirm-label='))
                    ->toBeTrue("{$name}: wire:confirm needs data-confirm-title and data-confirm-label");
            }
        }
    }

    expect(File::get(resource_path('views/components/layouts/app/sidebar.blade.php')))->toContain('<x-confirm-dialog />');
});

test('REQ-09-07 the verify-email page names the address the code was sent to', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'new.student@example.com']);

    $this->actingAs($user)->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Verify your email')
        ->assertSee('new.student@example.com');
});

test('REQ-09-08 toasts appear at the top centre of the screen', function () {
    $this->actingAs(User::factory()->student()->create())->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('class="pointer-events-none fixed inset-x-4 top-4 z-50 flex justify-center"');
});

test('REQ-09-09 the requester sidebar has no group heading and lists New Request, then My Requests', function () {
    $html = $this->actingAs(User::factory()->student()->create())->get(route('student.dashboard'))
        ->assertOk()
        ->getContent();

    $nav = Str::between($html, 'data-flux-navlist>', '</nav>');

    expect($nav)->not->toContain('Student')
        ->and(strpos($nav, 'New Request'))->toBeLessThan(strpos($nav, 'My Requests'))
        ->and($nav)->not->toContain('My Profile');
});

test('REQ-09-10 the incomplete-requirements notice is collapsed between "Who will transact?" and Review request', function () {
    $org = organisation();
    $heading = 'Important notice: incomplete requirements';

    $html = $this->actingAs(requester($org->ccs))->get(route('student.requests.create'))->assertOk()
        ->assertSeeInOrder(['5. Who will transact?', $heading, 'Review request'])
        ->getContent();

    $notice = Str::between($html, '<details', '</details>');
    expect(Str::before($notice, '>'))->not->toContain(' open')
        ->and(Str::between($notice, '<summary', '</summary>'))->toContain($heading)
        ->and(Str::after($notice, '</summary>'))->toContain('Submitting an online request does not automatically mean')
        ->and($html)->not->toContain(' inert');
});

test('REQ-09-11 the chosen credential shows its offices as a numbered list ending with the final check', function () {
    $org = organisation();

    $html = Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->set('form.credential_type_id', $org->tor->id)
        ->assertSee(['₱530.00', '7 working days', 'Offices that process it'])
        ->html();

    $html = credentialCard($html, $org->tor);   // every credential's card is on the page (REQ-15-15)
    $route = Str::between($html, '<ol role="list"', '</ol>');
    $stops = collect(explode('</li>', $route))->filter(fn (string $item) => str_contains($item, '<li'))
        ->map(fn (string $item) => trim(preg_replace('/\s+/', ' ', strip_tags(Str::after($item, '<li')))))
        ->map(fn (string $item) => Str::after($item, '> '))
        ->values()->all();

    expect($stops)->toBe([
        '1 '.$org->registrarOffice->name.' (prepare)',
        '2 Dean, College of Computer Studies (sign)',
        '3 Office of the VP for Finance (documentary stamp)',
        '4 Office of the Chancellor (sign)',
        '5 '.$org->registrarOffice->name.' (final quality check)',
    ]);

    // Requirements are a checklist below the route.
    expect(Str::after($html, '</ol>'))->toContain('Documentary requirements')->toContain('PSA-authenticated birth certificate');
});

test('REQ-09-18 signed-in pages stop at 96rem on wide screens and detail grids can shrink on phones', function () {
    $org = organisation();
    $registrar = User::factory()->registrar()->create();
    $request = submitRequest(requester($org->ccs), $org->free);

    $this->actingAs($registrar);

    expect($this->get(route('registrar.departments'))->assertOk()->getContent())->toContain('class="mx-auto w-full max-w-screen-2xl" data-page-width')
        ->and($this->get(route('registrar.requests.show', $request))->assertOk()->getContent())->toContain('grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]');
});

test('REQ-09-12 New Request and My Requests share one column and one section style', function () {
    $org = organisation();
    $owner = requester($org->ccs);
    submitRequest($owner, $org->free);
    $this->actingAs($owner);

    foreach (['student.requests.create', 'student.dashboard'] as $page) {
        $html = $this->get(route($page))->assertOk()->getContent();

        preg_match_all('/<section[^>]*class="([^"]*)"/', $html, $sections);

        expect($html)->toContain('class="mx-auto max-w-3xl space-y-6"')
            ->and($sections[1])->not->toBeEmpty()
            ->and(collect($sections[1])->reject(fn (string $class) => str_contains($class, 'rounded-xl border border-secondary/50 p-5 sm:p-6'))->all())->toBe([]);
    }

    $this->get(route('student.dashboard'))->assertSeeInOrder(['Your requests', 'Certification of Enrollment']);
});
