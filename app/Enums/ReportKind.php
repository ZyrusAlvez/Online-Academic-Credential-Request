<?php

namespace App\Enums;

use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\MonthlySummaryReport;
use App\Reports\OfficeDocumentsReport;
use App\Reports\Report;
use App\Reports\TransactionReport;

/**
 * The reports (REQ-34-05). Office Performance comes first and is the default. Office Documents is
 * opened from the dashboard's office table and is not in the list (REQ-34-12).
 */
enum ReportKind: string
{
    case Office = 'office';
    case Delay = 'delay';
    case Transactions = 'transactions';
    case Monthly = 'monthly';
    case OfficeDocuments = 'office-documents';

    /**
     * The four reports of the Reports list.
     *
     * @return list<self>
     */
    public static function listed(): array
    {
        return [self::Office, self::Delay, self::Transactions, self::Monthly];
    }

    public function report(): Report
    {
        return match ($this) {
            self::Office => new DepartmentReport,
            self::Delay => new DelayReport,
            self::Transactions => new TransactionReport,
            self::Monthly => new MonthlySummaryReport,
            self::OfficeDocuments => new OfficeDocumentsReport,
        };
    }
}
