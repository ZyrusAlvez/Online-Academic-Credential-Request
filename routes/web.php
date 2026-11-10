<?php

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
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(auth()->user()->role->homeRoute()))
        ->name('dashboard');

    Route::prefix('student')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/', Student\Dashboard::class)->name('dashboard');
        // My Profile became Settings → Profile; links in older notifications still land (REQ-15-02).
        Route::redirect('profile', '/settings/profile');
        Route::get('requests/new', Student\Requests\Create::class)->name('requests.create');
        Route::get('requests/{credentialRequest}', Student\Requests\Show::class)->name('requests.show');
        Route::get('requests/{credentialRequest}/edit', Student\Requests\Edit::class)->name('requests.edit');
    });

    // The Registrar works the requests, Initial Evaluation to Release (REQ-16-01..06, REQ-18-03, 05).
    Route::prefix('registrar')->name('registrar.')->middleware('role:registrar')->group(function () {
        Route::redirect('/', '/registrar/evaluation');
        Route::get('evaluation', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Evaluation->value)->name('evaluation');
        Route::get('payments', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Payment->value)->name('payments');
        Route::get('in-progress', Registrar\Queue::class)->defaults('queue', RegistrarQueue::InProgress->value)->name('in-progress');
        Route::get('final-check', Registrar\Queue::class)->defaults('queue', RegistrarQueue::FinalCheck->value)->name('final-check');
        Route::get('release', Registrar\Queue::class)->defaults('queue', RegistrarQueue::Release->value)->name('release');
        Route::get('requests/{credentialRequest}', Registrar\Requests\Show::class)->name('requests.show');
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
        Route::get('users', Admin\Users\Index::class)->name('users');
        Route::get('calendar', Admin\Calendar\Index::class)->name('calendar');
        Route::get('logs', Admin\Logs\Index::class)->name('logs');
    });

    Route::prefix('department')->name('department.')->middleware('role:department')->group(function () {
        Route::get('/', Department\Dashboard::class)->name('dashboard');
        Route::get('requests/{credentialRequest}', Department\Requests\Show::class)->name('requests.show');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('payment-proofs/{paymentProof}', [PaymentProofController::class, 'show'])->name('payment-proofs.show');
    Route::get('identity-documents/{identityDocument}', [IdentityDocumentController::class, 'show'])->name('identity-documents.show');
    Route::get('request-documents/{requestDocument}', [RequestDocumentController::class, 'show'])->name('request-documents.show');
    Route::get('notifications', Notifications\Index::class)->name('notifications');
});

Route::middleware(['auth'])->group(function () {
    // Requesters land on their profile (REQ-15-07); staff have only the password.
    Route::get('settings', fn () => redirect()->route(
        auth()->user()->hasRole(Role::Student) ? 'settings.profile' : 'settings.password'
    ))->name('settings');

    Route::get('settings/profile', Student\Profile::class)
        ->middleware(['verified', 'role:student'])->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Route::get('settings/notifications', Student\EmailNotifications::class)
        ->middleware('role:student')->name('settings.notifications');
});

require __DIR__.'/auth.php';
