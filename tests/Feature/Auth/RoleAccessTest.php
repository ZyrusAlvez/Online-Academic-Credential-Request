<?php

use App\Models\User;

// REQ-01-10 is superseded by REQ-18-02/03: the registrar lands on Initial Evaluation, the admin on the dashboard.
test('REQ-18-02 REQ-18-03 dashboard redirects each role to its home', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
    $this->get(route($route))->assertOk();
})->with([
    'student' => ['student', 'student.requests.create'],
    'registrar' => ['registrar', 'registrar.evaluation'],
    'department' => ['departmentStaff', 'department.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('REQ-01-32 a logged-in user opening the landing page goes to their role\'s home', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route($route));
})->with([
    'student' => ['student', 'student.requests.create'],
    'registrar' => ['registrar', 'registrar.evaluation'],
    'department' => ['departmentStaff', 'department.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('REQ-01-32 guests see the landing page', function () {
    $this->get('/')->assertOk()->assertSee('Create an account');
});

test('REQ-01-11 users cannot open another role\'s pages', function (string $state, array $forbidden) {
    $user = User::factory()->{$state}()->create();

    foreach ($forbidden as $route) {
        $this->actingAs($user)->get(route($route))->assertForbidden();
    }
})->with([
    'student' => ['student', ['registrar.evaluation', 'department.dashboard', 'admin.dashboard']],
    'registrar' => ['registrar', ['student.dashboard', 'department.dashboard', 'admin.dashboard']],
    'department' => ['departmentStaff', ['student.dashboard', 'registrar.evaluation', 'admin.dashboard']],
    'admin' => ['admin', ['student.dashboard', 'department.dashboard', 'registrar.evaluation']],
]);

test('guests are redirected to login', function () {
    $this->get(route('registrar.evaluation'))->assertRedirect(route('login'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});
