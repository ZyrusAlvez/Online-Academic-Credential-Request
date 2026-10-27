<?php

namespace App\Reports;

use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filters shared by the dashboard indicators, the reports and their CSV exports (spec 07):
 * a date range (this month by default), credential type, evaluator and office.
 */
final class ReportFilter
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(
        public readonly string $from = '',
        public readonly string $to = '',
        public readonly string $type = '',
        public readonly string $evaluator = '',
        public readonly string $office = '',
    ) {
        $this->start = self::date($from)?->startOfDay() ?? CarbonImmutable::now()->startOfMonth();
        $this->end = self::date($to)?->endOfDay() ?? CarbonImmutable::now()->endOfMonth();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            from: (string) ($input['from'] ?? ''),
            to: (string) ($input['to'] ?? ''),
            type: (string) ($input['type'] ?? ''),
            evaluator: (string) ($input['evaluator'] ?? ''),
            office: (string) ($input['office'] ?? ''),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'type' => $this->type,
            'evaluator' => $this->evaluator,
            'office' => $this->office,
        ], fn (string $value) => $value !== '');
    }

    /**
     * The end of the period, but never later than now: what can already be judged.
     */
    public function judgedUntil(): CarbonImmutable
    {
        return $this->end->min(CarbonImmutable::now());
    }

    /**
     * Requests matching the credential type, evaluator and office (a request matches an office it
     * has visited).
     *
     * @return Builder<CredentialRequest>
     */
    public function requests(): Builder
    {
        return CredentialRequest::query()
            ->when($this->type !== '', fn (Builder $q) => $q->where('credential_requests.credential_type_id', (int) $this->type))
            ->when($this->evaluator !== '', fn (Builder $q) => $q->where('credential_requests.evaluated_by', (int) $this->evaluator))
            ->when($this->office !== '', fn (Builder $q) => $q->whereHas('visits', fn (Builder $v) => $v->where('department_id', (int) $this->office)));
    }

    /**
     * Office visits of matching requests, restricted to the office when one is chosen.
     *
     * @return Builder<OfficeVisit>
     */
    public function visits(): Builder
    {
        return OfficeVisit::query()
            ->when($this->office !== '', fn (Builder $q) => $q->where('office_visits.department_id', (int) $this->office))
            ->when($this->type !== '' || $this->evaluator !== '', fn (Builder $q) => $q->whereHas('credentialRequest', fn (Builder $r) => $r
                ->when($this->type !== '', fn (Builder $r) => $r->where('credential_type_id', (int) $this->type))
                ->when($this->evaluator !== '', fn (Builder $r) => $r->where('evaluated_by', (int) $this->evaluator))));
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
