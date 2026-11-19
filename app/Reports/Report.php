<?php

namespace App\Reports;

/**
 * One of the client's reports (Proposed Workflow IX-B; REQ-07-13).
 */
interface Report
{
    public function title(): string;

    public function description(): string;

    /**
     * @return list<string>
     */
    public function columns(): array;

    /**
     * The filters the report shows and applies, besides the period (REQ-34-13).
     *
     * @return list<'type'|'evaluator'|'office'>
     */
    public function filters(): array;

    /**
     * The options of the report's Show filter (value => label); none when empty (REQ-34-13).
     *
     * @return array<string, string>
     */
    public function shows(): array;

    /**
     * @return iterable<list<string|int|float|null>>
     */
    public function rows(ReportFilter $filter): iterable;
}
