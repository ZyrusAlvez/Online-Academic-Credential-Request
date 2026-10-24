<?php

use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\RequestsCsvController;
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
        Route::get('requests/new', Student\Requests\Create::class)->name('requests.create');
        Route::get('requests/{credentialRequest}', Student\Requests\Show::class)->name('requests.show');
    });

    Route::prefix('registrar')->name('registrar.')->middleware('role:registrar')->group(function () {
        Route::get('/', Registrar\Dashboard::class)->name('dashboard');
        Route::get('requests', Registrar\Requests\Index::class)->name('requests');
        Route::get('requests/export', RequestsCsvController::class)->name('requests.export');
        Route::get('requests/{credentialRequest}', Registrar\Requests\Show::class)->name('requests.show');
        Route::get('departments', Registrar\Departments\Index::class)->name('departments');
        Route::get('users', Registrar\Users\Index::class)->name('users');
        Route::get('calendar', Registrar\Calendar\Index::class)->name('calendar');
        Route::get('credential-types', Registrar\CredentialTypes\Index::class)->name('credential-types');
        Route::get('credential-types/{credentialType}/workflow', Registrar\CredentialTypes\Workflow::class)->name('credential-types.workflow');
    });

    Route::prefix('department')->name('department.')->middleware('role:department')->group(function () {
        Route::get('/', Department\Dashboard::class)->name('dashboard');
        Route::get('requests/{credentialRequest}', Department\Requests\Show::class)->name('requests.show');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('payment-proofs/{paymentProof}', [PaymentProofController::class, 'show'])->name('payment-proofs.show');
    Route::get('notifications', Notifications\Index::class)->name('notifications');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
});

require __DIR__.'/auth.php';
