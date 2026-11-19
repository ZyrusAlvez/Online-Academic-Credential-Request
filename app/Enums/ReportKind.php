<?php

namespace App\Enums;

use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\MonthlySummaryReport;
use App\Reports\Report;
use App\Reports\TransactionReport;

/**
 * The four reports (REQ-34-05). Office Performance comes first and is the default.
 */
enum ReportKind: string
{
    case Office = 'office';
    case Delay = 'delay';
    case Transactions = 'transactions';
    case Monthly = 'monthly';

    public function report(): Report
    {
        return match ($this) {
            self::Office => new DepartmentReport,
            self::Delay => new DelayReport,
            self::Transactions => new TransactionReport,
            self::Monthly => new MonthlySummaryReport,
        };
    }
}
