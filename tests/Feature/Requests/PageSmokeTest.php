<?php

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

/*
| Every demo request page renders for the registrar, its owner and the office holding it, so a
| view error in any status shows up here.
*/

test('every request page renders in every status for each role that can see it', function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $registrar = User::where('role', Role::Registrar)->firstOrFail();

    foreach (CredentialRequest::with(['student', 'openVisit.department'])->get() as $request) {
        $this->actingAs($registrar)->get(route('registrar.requests.show', $request))->assertOk();
        $this->actingAs($request->student)->get(route('student.requests.show', $request))->assertOk();

        if ($request->openVisit && $request->openVisit->department->code !== 'REG') {
            $staff = User::where('department_id', $request->openVisit->department_id)->firstOrFail();
            $this->actingAs($staff)->get(route('department.requests.show', $request))->assertOk();
        }
    }

    foreach (['registrar.dashboard', 'registrar.requests', 'registrar.identities'] as $page) {
        $this->actingAs($registrar)->get(route($page))->assertOk();
    }

    $this->actingAs(User::where('email', 'vpf@example.com')->firstOrFail())->get(route('department.dashboard'))->assertOk();
    $this->actingAs(User::where('email', 'student@example.com')->firstOrFail())->get(route('student.dashboard'))->assertOk();
    $this->actingAs(User::where('email', 'student@example.com')->firstOrFail())->get(route('student.requests.create'))->assertOk();
});
