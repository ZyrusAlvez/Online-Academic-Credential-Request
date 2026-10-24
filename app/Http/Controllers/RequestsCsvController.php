<?php

namespace App\Http\Controllers;

use App\Models\CredentialRequest;
use App\Queries\RegistrarRequestFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestsCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', CredentialRequest::class);

        $query = RegistrarRequestFilter::fromArray($request->only(['search', 'status', 'type', 'office']))
            ->query()
            ->with(['student', 'credentialType', 'college', 'openVisit.department']);

        $filename = 'credential-requests-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tracking no.', 'Credential', 'Copies', 'Requester', 'Student number', 'College', 'Status', 'Currently at', 'Submitted', 'Accepted', 'Due', 'Adjusted due', 'Released', 'Processing working days']);

            foreach ($query->lazyById(200) as $row) {
                /** @var CredentialRequest $row */
                fputcsv($out, array_map([self::class, 'safeCell'], [
                    $row->reference_no,
                    $row->credentialType->name,
                    (string) $row->copies,
                    $row->student->name,
                    $row->student_number ?? '',
                    $row->college->name,
                    $row->status->label(),
                    $row->whereabouts(),
                    $row->submitted_at?->format('Y-m-d H:i') ?? '',
                    $row->accepted_at?->format('Y-m-d H:i') ?? '',
                    $row->due_at?->format('Y-m-d') ?? '',
                    $row->adjusted_due_at?->format('Y-m-d') ?? '',
                    $row->released_at?->format('Y-m-d H:i') ?? '',
                    $row->processing_working_days !== null ? (string) $row->processing_working_days : '',
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formula injection (cells starting with = + - @).
     */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
