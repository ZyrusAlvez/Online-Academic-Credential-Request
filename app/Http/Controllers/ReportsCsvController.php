<?php

namespace App\Http\Controllers;

use App\Enums\ReportKind;
use App\Models\CredentialRequest;
use App\Reports\ReportFilter;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One report as CSV with its filters (REQ-07-20).
 */
class ReportsCsvController extends Controller
{
    public function __invoke(Request $request, string $report): StreamedResponse
    {
        Gate::authorize('viewAny', CredentialRequest::class);

        $kind = ReportKind::tryFrom($report) ?? abort(404);
        $filter = ReportFilter::fromArray($request->only(['from', 'to', 'type', 'evaluator', 'office']));

        return Csv::download(
            "{$kind->value}-report-{$filter->start->format('Y-m-d')}-to-{$filter->end->format('Y-m-d')}.csv",
            $kind->report()->columns(),
            $kind->report()->rows($filter),
        );
    }
}
