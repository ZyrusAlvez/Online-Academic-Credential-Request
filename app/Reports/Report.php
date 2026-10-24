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
     * @return iterable<list<string|int|float|null>>
     */
    public function rows(ReportFilter $filter): iterable;
}
