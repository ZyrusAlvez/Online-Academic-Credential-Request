<?php

use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Shared fixtures for request-lifecycle tests.
*/

/**
 * A small organisation: Library, Cashier, Registrar office and two colleges, plus a
 * TOR-like credential type with Library → Cashier → Student's College → Registrar.
 *
 * @return object{library: Department, cashier: Department, registrarOffice: Department, ccs: Department, cba: Department, tor: CredentialType, free: CredentialType}
 */
function organisation(): object
{
    $library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
    $cashier = Department::factory()->create(['name' => 'Cashier / Finance Office', 'code' => 'FIN']);
    $registrarOffice = Department::factory()->registrarOffice()->create();
    $ccs = Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    $cba = Department::factory()->college()->create(['name' => 'College of Business and Accountancy', 'code' => 'CBA']);

    $tor = CredentialType::factory()->paid('300.00')
        ->withSteps([$library, $cashier, 'college', $registrarOffice])
        ->create(['name' => 'Transcript of Records']);

    $free = CredentialType::factory()
        ->withSteps([$registrarOffice])
        ->create(['name' => 'Certificate of Enrollment']);

    return (object) compact('library', 'cashier', 'registrarOffice', 'ccs', 'cba', 'tor', 'free');
}

/**
 * Submit a request through the real workflow.
 *
 * @param  array<string, mixed>  $overrides
 */
function submitRequest(User $student, CredentialType $type, Department $college, array $overrides = []): CredentialRequest
{
    return app(RequestWorkflow::class)->submit($student, array_merge([
        'credential_type_id' => $type->id,
        'college_id' => $college->id,
        'student_number' => '20-1234-567',
        'program' => 'BS Computer Science',
        'student_status' => 'alumni',
        'last_school_year' => '2023-2024',
        'purpose' => 'Employment',
        'copies' => 1,
    ], $overrides));
}
