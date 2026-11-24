<?php

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
