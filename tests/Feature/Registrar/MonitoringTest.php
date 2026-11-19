<?php

use App\Enums\RequestStatus;
use App\Livewire\Registrar\Records\Index;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Reports\DepartmentReport;
use App\Reports\ReportFilter;
use Database\Seeders\DemoRequestSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->admin = User::factory()->admin()->create();   // the dashboard is the Admin's (REQ-18-02)
    $this->owner = requester($this->org->ccs);
});

afterEach(fn () => Carbon::setTestNow());

function acceptedAt(string $date, $type = null): CredentialRequest
{
    Carbon::setTestNow($date);
    $request = acceptRequest(submitRequest(test()->owner, $type ?? test()->org->free), test()->registrar);
    Carbon::setTestNow();

    return $request;
}

test('REQ-07-02 requests being processed past their due date are overdue and filterable', function () {
    $late = acceptedAt('2026-01-05 09:00:00');
    $onTime = acceptedAt(now()->toDateTimeString());

    expect($late->isOverdue())->toBeTrue()
        ->and($onTime->isOverdue())->toBeFalse();

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'overdue')
        ->assertSee($late->reference_no)
        ->assertDontSee($onTime->reference_no)
        ->assertSee('Request overdue')->assertSeeHtml('data-request-overdue');   // "Overdue" before REQ-30-10

    // In progress: its row opens the record (REQ-17-12).
    $this->actingAs($this->registrar)->get(route('records.show', $late))->assertSee('Overdue since');
});

test('REQ-18-08 Records\' "Needs the Registrar" has walk-in payments, not online ones', function () {
    $new = submitRequest($this->owner, $this->org->free);
    // A proof to verify: an online one is the Cashier's, a walk-in one the Registrar's.
    $online = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($online);
    $walkIn = walkIn(evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar));
    uploadPayment($walkIn);

    Livewire::actingAs($this->registrar)->test(Index::class)->set('status', 'needs_registrar')
        ->assertSee($new->reference_no)
        ->assertSee($walkIn->reference_no)
        ->assertDontSee($online->reference_no);
});

test('REQ-34-02 the office table\'s pending counts follow the paper', function () {
    acceptedAt(now()->toDateTimeString(), $this->org->tor);
    acceptedAt(now()->toDateTimeString(), $this->org->tor);
    $atDean = acceptedAt(now()->toDateTimeString(), $this->org->tor);
    passOffice($atDean, $this->registrar);
    workflow()->acceptReceipt(openVisit($atDean), User::factory()->departmentStaff($this->org->ccs)->create());   // delivered (REQ-17-18)

    $pending = DepartmentReport::offices(new ReportFilter)->pluck('pending', 'name');
    expect($pending['Office of the Registrar'])->toBe(2)
        ->and($pending['College of Computer Studies'])->toBe(1);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertSee(route('records', ['status' => 'processing', 'office' => $this->org->registrarOffice->id]))
        ->assertSee(route('records', ['status' => 'processing', 'office' => $this->org->ccs->id]));
});

test('REQ-07-07 the request list can be filtered by the office holding the document', function () {
    $atDean = acceptedAt(now()->toDateTimeString(), $this->org->tor);
    passOffice($atDean, $this->registrar);
    workflow()->acceptReceipt(openVisit($atDean), User::factory()->departmentStaff($this->org->ccs)->create());   // delivered (REQ-17-18)
    $atRegistrar = acceptedAt(now()->toDateTimeString());

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'processing')
        ->set('office', (string) $this->org->ccs->id)
        ->assertSee($atDean->reference_no)
        ->assertDontSee($atRegistrar->reference_no);
});

test('REQ-07-09 the demo seeder creates accounts and requests in every status still in use', function () {
    CredentialRequest::query()->delete();

    $this->seed(DemoRequestSeeder::class);

    expect(User::where('email', 'student@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'vpf@example.com')->value('department_id'))->not->toBeNull()
        ->and(CredentialRequest::distinct()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(collect(RequestStatus::cases())->reject(fn ($s) => $s === RequestStatus::ReturnedForCustomerAction)->map->value->sort()->values()->all())
        ->and(CredentialRequest::overdue()->count())->toBeGreaterThan(0);
});
