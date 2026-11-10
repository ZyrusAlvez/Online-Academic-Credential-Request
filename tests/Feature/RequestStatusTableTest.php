<?php

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Spec 19: every request status is a row of request_statuses, and the database refuses any other.
*/

test('REQ-19-01 request_statuses holds each status with its label, meaning, order and final flag', function () {
    $row = DB::table('request_statuses')->where('value', 'pending_compliance')->first();

    expect($row)->not->toBeNull()
        ->and($row->label)->toBe('Pending compliance')
        ->and($row->meaning)->not->toBe('')
        ->and((int) $row->sort_order)->toBe(3)
        ->and((bool) $row->is_final)->toBeFalse()
        ->and((bool) DB::table('request_statuses')->where('value', 'released')->value('is_final'))->toBeTrue();
});

test('REQ-19-02 request_statuses lists exactly the RequestStatus cases, in order, with their labels and final flags', function () {
    $rows = DB::table('request_statuses')->orderBy('sort_order')->get()
        ->map(fn (object $row): array => [$row->value, $row->label, (bool) $row->is_final])
        ->all();

    $cases = array_map(fn (RequestStatus $status): array => [$status->value, $status->label(), $status->isFinal()], RequestStatus::cases());

    expect($rows)->toBe($cases);
});

test('REQ-19-03 a request status that is not in request_statuses is refused', function () {
    $request = CredentialRequest::factory()->create();

    expect(fn () => DB::table('credential_requests')->where('id', $request->id)->update(['status' => 'approved']))
        ->toThrow(QueryException::class, 'foreign key constraint');
});

test('REQ-19-03 a ledger event with a status that is not in request_statuses is refused', function () {
    $request = CredentialRequest::factory()->create();

    expect(fn () => DB::table('request_events')->insert([
        'credential_request_id' => $request->id, 'type' => 'submitted', 'from_status' => null, 'to_status' => 'approved',
    ]))->toThrow(QueryException::class, 'foreign key constraint');

    expect(fn () => DB::table('request_events')->insert([
        'credential_request_id' => $request->id, 'type' => 'submitted', 'from_status' => 'approved', 'to_status' => 'submitted',
    ]))->toThrow(QueryException::class, 'foreign key constraint');
});

test('REQ-19-03 a due-date pause that resumes to a status not in request_statuses is refused', function () {
    $request = CredentialRequest::factory()->create();

    expect(fn () => DB::table('due_date_pauses')->insert([
        'credential_request_id' => $request->id, 'reason' => 'Holiday', 'resume_status' => 'approved',
        'started_at' => now(), 'started_by' => User::factory()->create()->id,
    ]))->toThrow(QueryException::class, 'foreign key constraint');
});

test('REQ-19-04 the migrations alone create every status row, without seeding', function () {
    // RefreshDatabase runs the migrations only; no seeder has run here.
    expect(DB::table('request_statuses')->count())->toBe(count(RequestStatus::cases()));
});
