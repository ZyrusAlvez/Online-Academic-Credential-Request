<?php

namespace App\Enums;

use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\EvaluatorReport;
use App\Reports\MonthlySummaryReport;
use App\Reports\Report;
use App\Reports\TransactionReport;

/**
 * The five reports of the Registrar's performance (REQ-34-05). Office Performance comes first and
 * is the default.
 */
enum ReportKind: string
{
    case Office = 'office';
    case Evaluator = 'evaluator';
    case Transactions = 'transactions';
    case Delay = 'delay';
    case Monthly = 'monthly';

    public function report(): Report
    {
        return match ($this) {
            self::Office => new DepartmentReport,
            self::Evaluator => new EvaluatorReport,
            self::Transactions => new TransactionReport,
            self::Delay => new DelayReport,
            self::Monthly => new MonthlySummaryReport,
        };
    }
}
