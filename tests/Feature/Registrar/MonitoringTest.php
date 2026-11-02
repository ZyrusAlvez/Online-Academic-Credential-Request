<?php

use App\Enums\RequestStatus;
use App\Livewire\Registrar\Requests\Index;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Support\Csv;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
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
        ->assertSee('Overdue');

    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $late))->assertSee('Overdue since');
});

test('REQ-07-03 the dashboard shows the work-queue counts', function () {
    submitRequest($this->owner, $this->org->free);                          // needs the Registrar
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar); // awaiting payment
    acceptedAt('2026-01-05 09:00:00');                                      // overdue

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Needs the Registrar', '1', 'Pending compliance', '0', 'Awaiting payment', '1', 'Being processed', '1', 'Ready for release', '0', 'Overdue', '1']);
});

test('REQ-07-05 the dashboard shows which offices hold documents, largest first', function () {
    acceptedAt(now()->toDateTimeString(), $this->org->tor);
    acceptedAt(now()->toDateTimeString(), $this->org->tor);
    $atDean = acceptedAt(now()->toDateTimeString(), $this->org->tor);
    passOffice($atDean, $this->registrar);

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertSeeInOrder(['Where requests are waiting', 'Office of the Registrar', '2', 'College of Computer Studies', '1'])
        ->assertSee(route('registrar.requests', ['status' => 'processing', 'office' => $this->org->ccs->id]));
});

test('REQ-07-06 the dashboard lists the most overdue requests', function () {
    $late = acceptedAt('2026-01-05 09:00:00');

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertSeeInOrder(['Most overdue', $late->reference_no]);
});

test('REQ-07-07 the request list can be filtered by the office holding the document', function () {
    $atDean = acceptedAt(now()->toDateTimeString(), $this->org->tor);
    passOffice($atDean, $this->registrar);
    $atRegistrar = acceptedAt(now()->toDateTimeString());

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'processing')
        ->set('office', (string) $this->org->ccs->id)
        ->assertSee($atDean->reference_no)
        ->assertDontSee($atRegistrar->reference_no);
});

test('REQ-07-08 the filtered list downloads as CSV and is registrar-only', function () {
    $request = acceptedAt(now()->toDateTimeString(), $this->org->tor);
    $other = submitRequest($this->owner, $this->org->free);

    $response = $this->actingAs($this->registrar)->get(route('registrar.requests.export', ['status' => 'processing']));
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('"Tracking no.",Credential,Copies,Requester')
        ->toContain('Accepted,Due,"Adjusted due",Released,"Processing working days"')
        ->toContain($request->reference_no)
        ->not->toContain($other->reference_no);

    $this->actingAs($this->owner)->get(route('registrar.requests.export'))->assertForbidden();
});

test('REQ-07-08 CSV cells cannot inject spreadsheet formulas', function () {
    expect(Csv::safeCell('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(Csv::safeCell('Juan'))->toBe('Juan');
});

test('REQ-07-09 the demo seeder creates accounts and requests in every status', function () {
    CredentialRequest::query()->delete();

    $this->seed(DemoSeeder::class);

    expect(User::where('email', 'student@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'vpf@example.com')->value('department_id'))->not->toBeNull()
        ->and(CredentialRequest::distinct()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(collect(RequestStatus::cases())->map->value->sort()->values()->all())
        ->and(CredentialRequest::overdue()->count())->toBeGreaterThan(0);
});
