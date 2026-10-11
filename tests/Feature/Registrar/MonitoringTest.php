<?php

use App\Enums\RequestStatus;
use App\Http\Controllers\RequestsCsvController;
use App\Livewire\Registrar\Requests\Index;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->workflow = app(RequestWorkflow::class);
    $this->registrar = User::factory()->registrar()->create();
    $this->student = User::factory()->student()->create();
    $this->librarian = User::factory()->departmentStaff($this->org->library)->create();
});

function routeAt(string $date, $type = null): CredentialRequest
{
    Carbon::setTestNow($date);
    $request = submitRequest(test()->student, test()->org->free, test()->org->ccs);
    if ($type) {
        $request->forceFill(['credential_type_id' => $type->id])->save();
    }
    test()->workflow->approve($request, test()->registrar);
    Carbon::setTestNow();

    return $request->fresh();
}

test('REQ-07-01 the due date counts weekdays only', function () {
    $this->org->tor->update(['processing_days' => 3]);

    // Friday 2026-10-09 10:00 + 3 weekdays = Wednesday 2026-10-14 10:00
    $request = routeAt('2026-10-09 10:00:00', $this->org->tor);

    expect($request->due_at->toDateTimeString())->toBe('2026-10-14 10:00:00');
});

test('REQ-07-02 in-progress requests past their due date are overdue and filterable', function () {
    $late = routeAt('2026-01-05 09:00:00');
    $onTime = routeAt(now()->toDateTimeString());

    expect($late->isOverdue())->toBeTrue()
        ->and($onTime->isOverdue())->toBeFalse()
        ->and(CredentialRequest::overdue()->pluck('id')->all())->toBe([$late->id]);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'overdue')
        ->assertSee($late->reference_no)
        ->assertDontSee($onTime->reference_no)
        ->assertSee('Overdue');

    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $late))->assertSee('Overdue since');
});

test('REQ-07-03 the dashboard shows status counts and this month\'s totals', function () {
    submitRequest($this->student, $this->org->free, $this->org->ccs);          // registrar review
    submitRequest($this->student, $this->org->tor, $this->org->ccs);           // awaiting payment
    routeAt('2026-01-05 09:00:00');                                            // overdue

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Needs review', '1', 'Awaiting payment', '1', 'In progress', '1', 'Ready for release', '0', 'Overdue', '1'])
        ->assertSeeInOrder(['Submitted this month', '2']);
});

test('REQ-07-04 average turnaround covers the last 30 days', function () {
    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee('—');

    $request = routeAt(now()->subDays(3)->toDateTimeString());
    Carbon::setTestNow(now()->subDay());
    $this->workflow->sign($request->currentStep()->firstOrFail(), $this->registrar);   // REG step → ready
    Carbon::setTestNow();

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee('2 days');
});

test('REQ-07-05 the dashboard shows where requests are waiting, largest first', function () {
    routeAt(now()->toDateTimeString(), $this->org->tor);
    routeAt(now()->toDateTimeString(), $this->org->tor);
    routeAt(now()->toDateTimeString());   // at the Registrar office

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertSeeInOrder(['Where requests are waiting', 'Library', '2', 'Office of the Registrar', '1'])
        ->assertSee(route('registrar.requests', ['status' => 'open', 'office' => $this->org->library->id]));
});

test('REQ-07-06 the dashboard lists the most overdue requests', function () {
    $late = routeAt('2026-01-05 09:00:00');

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertSeeInOrder(['Most overdue', $late->reference_no]);
});

test('REQ-07-07 the request list can be filtered by the current office', function () {
    $atLibrary = routeAt(now()->toDateTimeString(), $this->org->tor);
    $atRegistrar = routeAt(now()->toDateTimeString());

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'open')
        ->set('office', (string) $this->org->library->id)
        ->assertSee($atLibrary->reference_no)
        ->assertDontSee($atRegistrar->reference_no);
});

test('REQ-07-08 the filtered list downloads as CSV and is registrar-only', function () {
    $request = routeAt(now()->toDateTimeString(), $this->org->tor);
    $other = submitRequest($this->student, $this->org->free, $this->org->ccs);

    $response = $this->actingAs($this->registrar)->get(route('registrar.requests.export', ['status' => 'open', 'office' => $this->org->library->id]));
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Reference,Credential,Copies,Student')
        ->toContain($request->reference_no)
        ->toContain('Library')
        ->not->toContain($other->reference_no);

    $this->actingAs($this->student)->get(route('registrar.requests.export'))->assertForbidden();
});

test('REQ-07-08 CSV cells cannot inject spreadsheet formulas', function () {
    expect(RequestsCsvController::safeCell('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(RequestsCsvController::safeCell('Juan'))->toBe('Juan');
});

test('REQ-07-09 the demo seeder creates accounts and requests in every status', function () {
    Storage::fake('local');
    CredentialRequest::query()->delete();

    $this->seed(DemoSeeder::class);

    expect(User::where('email', 'student@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'lib@uphsl.edu.ph')->value('department_id'))->not->toBeNull()
        ->and(CredentialRequest::distinct()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(collect(RequestStatus::cases())->map->value->sort()->values()->all())
        ->and(CredentialRequest::overdue()->count())->toBeGreaterThan(0);
});
