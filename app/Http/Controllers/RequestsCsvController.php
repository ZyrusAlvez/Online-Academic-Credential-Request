<?php

namespace App\Http\Controllers;

use App\Models\CredentialRequest;
use App\Queries\RegistrarRequestFilter;
use App\Support\Csv;
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

        return Csv::download(
            'credential-requests-'.now()->format('Y-m-d-His').'.csv',
            ['Tracking no.', 'Credential', 'Copies', 'Requester', 'Student number', 'College', 'Status', 'Currently at', 'Submitted', 'Accepted', 'Due', 'Adjusted due', 'Released', 'Processing working days'],
            $query->lazyById(200)->map(fn (CredentialRequest $row): array => [
                $row->reference_no,
                $row->credentialType->name,
                $row->copies,
                $row->student->name,
                $row->student_number,
                $row->college->name,
                $row->status->label(),
                $row->whereabouts(),
                $row->submitted_at?->format('Y-m-d H:i'),
                $row->accepted_at?->format('Y-m-d H:i'),
                $row->due_at?->format('Y-m-d'),
                $row->adjusted_due_at?->format('Y-m-d'),
                $row->released_at?->format('Y-m-d H:i'),
                $row->processing_working_days,
            ]),
        );
    }
}
