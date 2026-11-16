<?php

use App\Enums\DepartmentQueue;
use App\Enums\HelpTopic;
use App\Enums\RegistrarQueue;
use App\Enums\Role;
use App\Http\Controllers\IdentityDocumentController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\RequestDocumentController;
use App\Livewire\Admin;
use App\Livewire\Department;
use App\Livewire\Notifications;
use App\Livewire\Registrar;
use App\Livewire\Student;
use App\Models\CredentialRequest;
use App\Support\DepartmentLink;
use App\Support\RegistrarLink;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

// Guests only: the guest middleware sends a logged-in user to /dashboard, their role's home (REQ-01-32).
Route::view('/', 'welcome')->middleware('guest')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(auth()->user()->role->homeRoute()))
        ->name('dashboard');

    Route::prefix('student')->name('student.')->middleware('role:student')->group(function () {
        // Request Tracker lives at requests; /student leads there (REQ-09-21).
        Route::redirect('/', '/student/requests');
        Route::get('requests', Student\Dashboard::class)->name('dashboard');
        // My Profile became Settings → Profile; links in older notifications still land (REQ-15-02).
        Route::redirect('profile', '/settings/profile');
        // A requester's request pages live under requests/, plural like every collection (REQ-09-21).
        Route::get('requests/new', Student\Requests\Create::class)->name('requests.create');
        Route::get('requests/{credentialRequest}', Student\Requests\Show::class)->name('requests.show');
        Route::get('requests/{credentialRequest}/edit', Student\Requests\Edit::class)->name('requests.edit');
        // The singular request/… addresses, in emails sent while they were in use, move to requests/… (REQ-09-21).
        Route::permanentRedirect('request/{path}', '/student/requests/{path}')
            ->where('path', '[A-Za-z0-9-]+(/edit)?');
        // Help: one page of questions per topic, the first by default (REQ-22-02).
        Route::redirect('help', '/student/help/'.HelpTopic::first()->value)->name('help.index');
        Route::get('help/{topic}', Student\Help::class)->name('help');
    });

    // The Registrar works the requests, Initial Evaluation to Release (REQ-16-01..06, REQ-18-03, 05).
    Route::prefix('registrar')->name('registrar.')->middleware('role:registrar')->group(function () {
        Route::redirect('/', '/registrar/evaluation');
        Route::get('evaluation', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Evaluation->value)->name('evaluation');
        Route::get('payments', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Payment->value)->name('payments');
        Route::get('in-progress', Registrar\Queue::class)->defaults('queue', RegistrarQueue::InProgress->value)->name('in-progress');
        Route::get('final-check', Registrar\Queue::class)->defaults('queue', RegistrarQueue::FinalCheck->value)->name('final-check');
        Route::get('release', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Release->value)->name('release');
        // A request's working page lives under the queue it is in, e.g. payments/rc26-00042 (REQ-16-26).
        // In Progress has none: its rows forward and accept receipt in place (REQ-17-13).
        Route::get('evaluation/{credentialRequest}', Registrar\Requests\Show::class)->defaults('queue', RegistrarQueue::Evaluation->value)->name('evaluation.show');
        Route::get('payments/{credentialRequest}', Registrar\Requests\Show::class)->defaults('queue', RegistrarQueue::Payment->value)->name('payments.show');
        Route::get('final-check/{credentialRequest}', Registrar\Requests\Show::class)->defaults('queue', RegistrarQueue::FinalCheck->value)->name('final-check.show');
        Route::get('release/{credentialRequest}', Registrar\Requests\Show::class)->defaults('queue', RegistrarQueue::Release->value)->name('release.show');
        // The former address, in notifications and emails: to wherever the request is now.
        Route::get('requests/{credentialRequest}', fn (CredentialRequest $credentialRequest) => redirect(RegistrarLink::to($credentialRequest)))
            ->name('requests.show');
    });

    // Records, shared by the Registrar and the Admin (REQ-16-07, REQ-18-06).
    Route::middleware('role:registrar,admin')->group(function () {
        Route::get('records', Registrar\Records\Index::class)->name('records');
        Route::get('records/{credentialRequest}', Registrar\Records\Show::class)->name('records.show');
    });

    // The Admin: dashboard, reports and administration (REQ-18-02, 04).
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', Admin\Dashboard::class)->name('dashboard');
        Route::get('reports', Admin\Reports\Index::class)->name('reports');
        Route::get('credential-types', Admin\CredentialTypes\Index::class)->name('credential-types');
        Route::get('credential-types/{credentialType}/workflow', Admin\CredentialTypes\Workflow::class)->name('credential-types.workflow');
        Route::get('departments', Admin\Departments\Index::class)->name('departments');
        Route::get('programs', Admin\Programs\Index::class)->name('programs');
        Route::get('purposes', Admin\Purposes\Index::class)->name('purposes');
        Route::get('accounts', Admin\Users\Index::class)->name('accounts');
        Route::get('calendar', Admin\Calendar\Index::class)->name('calendar');
        Route::get('logs', Admin\Logs\Index::class)->name('logs');
    });

    // A department office's lists, and a request under the list it is in (REQ-24-01, 06).
    Route::prefix('department')->name('department.')->middleware('role:department')->group(function () {
        Route::redirect('/', '/department/incoming')->name('dashboard');
        foreach (DepartmentQueue::cases() as $queue) {
            $path = str_replace('_', '-', $queue->value);
            Route::get($path, Department\Queue::class)->defaults('queue', $queue->value)->name(Str::after($queue->routeName(), 'department.'));
            Route::get($path.'/{credentialRequest}', Department\Requests\Show::class)->defaults('queue', $queue->value)->name(Str::after($queue->showRouteName(), 'department.'));
        }
        // The former address, in notifications and emails: to the list the request is in now.
        Route::get('requests/{credentialRequest}', function (CredentialRequest $credentialRequest) {
            Gate::authorize('view', $credentialRequest);

            return redirect(DepartmentLink::to($credentialRequest, (int) auth()->user()->department_id));
        })->name('requests.show');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('payment-proofs/{paymentProof}', [PaymentProofController::class, 'show'])->name('payment-proofs.show');
    Route::get('identity-documents/{identityDocument}', [IdentityDocumentController::class, 'show'])->name('identity-documents.show');
    Route::get('request-documents/{requestDocument}', [RequestDocumentController::class, 'show'])->name('request-documents.show');
    Route::get('notifications', Notifications\Index::class)->name('notifications');
});

Route::middleware(['auth'])->group(function () {
    // Requesters land on their profile (REQ-15-07); staff on their password.
    Route::get('settings', fn () => redirect()->route(
        auth()->user()->hasRole(Role::Student) ? 'settings.profile' : 'settings.password'
    ))->name('settings');

    Route::get('settings/profile', Student\Profile::class)
        ->middleware(['verified', 'role:student'])->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Route::get('settings/notifications', Notifications\EmailSettings::class)
        ->middleware('role:student,registrar,department')->name('settings.notifications');
});

require __DIR__.'/auth.php';
