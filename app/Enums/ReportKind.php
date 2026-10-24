<?php

namespace App\Enums;

use App\Reports\ComplianceReport;
use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\EvaluatorReport;
use App\Reports\PeriodicReport;
use App\Reports\ReleaseReport;
use App\Reports\Report;
use App\Reports\SignatoryReport;
use App\Reports\TrackingReport;

/**
 * The client's ten reports (REQ-07-13).
 */
enum ReportKind: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Evaluator = 'evaluator';
    case Department = 'department';
    case Signatory = 'signatory';
    case Tracking = 'tracking';
    case Delay = 'delay';
    case Compliance = 'compliance';
    case Release = 'release';

    public function report(): Report
    {
        return match ($this) {
            self::Daily => new PeriodicReport('day'),
            self::Weekly => new PeriodicReport('week'),
            self::Monthly => new PeriodicReport('month'),
            self::Evaluator => new EvaluatorReport,
            self::Department => new DepartmentReport,
            self::Signatory => new SignatoryReport,
            self::Tracking => new TrackingReport,
            self::Delay => new DelayReport,
            self::Compliance => new ComplianceReport,
            self::Release => new ReleaseReport,
        };
    }
}
