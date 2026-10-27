<?php

use App\Models\User;

test('REQ-01-10 dashboard redirects each role to its home', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
    $this->get(route($route))->assertOk();
})->with([
    'student' => ['student', 'student.requests.create'],
    'registrar' => ['registrar', 'registrar.dashboard'],
    'department' => ['departmentStaff', 'department.dashboard'],
]);

test('REQ-01-11 users cannot open another role\'s pages', function (string $state, array $forbidden) {
    $user = User::factory()->{$state}()->create();

    foreach ($forbidden as $route) {
        $this->actingAs($user)->get(route($route))->assertForbidden();
    }
})->with([
    'student' => ['student', ['registrar.dashboard', 'department.dashboard']],
    'registrar' => ['registrar', ['student.dashboard', 'department.dashboard']],
    'department' => ['departmentStaff', ['student.dashboard', 'registrar.dashboard']],
]);

test('guests are redirected to login', function () {
    $this->get(route('registrar.dashboard'))->assertRedirect(route('login'));
});
