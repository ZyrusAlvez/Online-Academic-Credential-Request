<?php

use App\Enums\ReportKind;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Support\RegistrarLink;
use Database\Seeders\DemoRequestSeeder;
use Illuminate\Support\Facades\Storage;

/*
| Every demo request page renders for the registrar, its owner and the office holding it, so a
| view error in any status shows up here.
*/

test('every request page renders in every status for each role that can see it', function () {
    Storage::fake('local');
    $this->seed(DemoRequestSeeder::class);
    $registrar = User::where('role', Role::Registrar)->firstOrFail();

    foreach (CredentialRequest::with(['student', 'openVisit.department'])->get() as $request) {
        $this->actingAs($registrar)->get(RegistrarLink::to($request))->assertOk();

        // A walk-in request not linked to an account has no owner's page (REQ-26-09).
        if ($request->student !== null) {
            $this->actingAs($request->student)->get(route('student.requests.show', $request))->assertOk();
        }

        if ($request->openVisit && $request->openVisit->department->code !== 'REG') {
            $staff = User::where('department_id', $request->openVisit->department_id)->firstOrFail();
            $this->actingAs($staff)->followingRedirects()->get(route('department.requests.show', $request))->assertOk();
        }
    }

    foreach (['registrar.walk-in', 'registrar.evaluation', 'registrar.payments', 'registrar.outgoing', 'registrar.final-check', 'registrar.release', 'records'] as $page) {
        $this->actingAs($registrar)->get(route($page))->assertOk();
    }

    // The cashier's list and each online payment awaiting validation (REQ-25-04, 05).
    $cashier = User::where('role', Role::Cashier)->firstOrFail();
    $this->actingAs($cashier)->get(route('cashier.payments'))->assertOk();
    $this->actingAs($cashier)->get(route('cashier.payments', ['waiting' => 1]))->assertOk();
    foreach (CredentialRequest::where('status', 'payment_verification')->where('channel', 'online')->get() as $request) {
        $this->actingAs($cashier)->get(route('cashier.payments.show', $request))->assertOk();
    }

    // The first admin (INITIAL_ADMIN_*; there is no demo admin): its pages, and every record (REQ-18-06).
    $admin = User::where('email', config('uphsl.initial_admin.email'))->firstOrFail();

    foreach (CredentialRequest::all() as $request) {
        $this->actingAs($registrar)->get(route('records.show', $request))->assertOk();
        $this->actingAs($admin)->get(route('records.show', $request))->assertOk();
    }

    $this->actingAs($registrar)->get(route('registrar.evaluation', ['waiting' => 1]))->assertOk();

    foreach (['admin.dashboard', 'records', 'admin.credential-types', 'admin.departments', 'admin.programs', 'admin.purposes', 'admin.accounts', 'admin.calendar', 'admin.logs'] as $page) {
        $this->actingAs($admin)->get(route($page))->assertOk();
    }

    foreach (ReportKind::cases() as $kind) {
        $period = ['from' => now()->subDays(60)->format('Y-m-d'), 'to' => now()->format('Y-m-d')];
        $this->actingAs($admin)->get(route('admin.reports', ['report' => $kind->value, ...$period]))->assertOk();
    }

    foreach (['department.incoming', 'department.to-validate', 'department.history'] as $page) {
        $this->actingAs(User::where('email', 'vpf@example.com')->firstOrFail())->get(route($page))->assertOk();
    }
    $this->actingAs(User::where('email', 'student@example.com')->firstOrFail())->get(route('student.dashboard'))->assertOk();
    $this->actingAs(User::where('email', 'student@example.com')->firstOrFail())->get(route('student.requests.create'))->assertOk();
    $this->actingAs(User::where('email', 'student@example.com')->firstOrFail())->get(route('settings.profile'))->assertOk();
});
