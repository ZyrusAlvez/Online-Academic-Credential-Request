<?php

use App\Livewire\Registrar\CredentialTypes\Index as CredentialTypesIndex;
use App\Livewire\Registrar\Departments\Index as DepartmentsIndex;
use App\Livewire\Registrar\Users\Index as UsersIndex;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const VISIBILITY_TOGGLE = 'Toggle password visibility';

function toggleCount(string $html): int
{
    return substr_count($html, 'aria-label="'.VISIBILITY_TOGGLE.'"');
}

test('REQ-09-01 every password field on the sign-in pages has a show/hide toggle', function () {
    expect(toggleCount($this->get(route('login'))->getContent()))->toBe(1)
        ->and(toggleCount($this->get(route('register'))->getContent()))->toBe(2)
        ->and(toggleCount($this->get(route('password.reset', 'token'))->getContent()))->toBe(2);

    $this->actingAs(User::factory()->create());

    expect(toggleCount($this->get(route('password.confirm'))->getContent()))->toBe(1)
        ->and(toggleCount($this->get(route('settings.password'))->getContent()))->toBe(3);
});

test('REQ-09-01 the staff account password field has a show/hide toggle', function () {
    Livewire::actingAs(User::factory()->registrar()->create())->test(UsersIndex::class)
        ->assertSeeHtml('aria-label="'.VISIBILITY_TOGGLE.'"');
});

test('REQ-09-02 new-password fields state the minimum length', function () {
    $this->get(route('register'))->assertSee('At least 8 characters.');
    $this->get(route('password.reset', 'token'))->assertSee('At least 8 characters.');

    $this->actingAs(User::factory()->create())
        ->get(route('settings.password'))->assertSee('At least 8 characters.');
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
    $this->get(route('settings.profile'))->assertSee($title('Profile settings'), false);
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

test('REQ-09-07 the verify-email page names the address the link was sent to', function () {
    $user = User::factory()->unverified()->create(['email' => 'new.student@example.com']);

    $this->actingAs($user)->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Verify your email')
        ->assertSee('new.student@example.com');
});
