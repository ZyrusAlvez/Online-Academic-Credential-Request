<?php

use App\Livewire\Department;
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
    });

    Route::prefix('registrar')->name('registrar.')->middleware('role:registrar')->group(function () {
        Route::get('/', Registrar\Dashboard::class)->name('dashboard');
    });

    Route::prefix('department')->name('department.')->middleware('role:department')->group(function () {
        Route::get('/', Department\Dashboard::class)->name('dashboard');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
});

require __DIR__.'/auth.php';
