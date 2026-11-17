<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * A department office's lists: documents sent to it, to approve, waiting for the Registrar to
 * collect them, and what it did (spec 24, REQ-31-05).
 */
enum DepartmentQueue: string
{
    case Incoming = 'incoming';
    case ToValidate = 'to_validate';
    case Outgoing = 'outgoing';
    case History = 'history';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Incoming',
            self::ToValidate => 'To Validate',
            self::Outgoing => 'Outgoing',
            self::History => 'History',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Incoming => 'Documents sent to your office.',
            self::ToValidate => 'Documents to approve or return.',
            self::Outgoing => 'Documents waiting for the Registrar to collect.',
            self::History => 'What your office did.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Incoming => 'inbox-arrow-down',
            self::ToValidate => 'clipboard-document-check',
            self::Outgoing => 'arrow-up-tray',
            self::History => 'clock',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Incoming => 'department.incoming',
            self::ToValidate => 'department.to-validate',
            self::Outgoing => 'department.outgoing',
            self::History => 'department.history',
        };
    }

    /** The request's page under this list (REQ-24-06). */
    public function showRouteName(): string
    {
        return $this->routeName().'.show';
    }

    public function emptyText(): string
    {
        return match ($this) {
            self::Incoming => 'Nothing has been sent to your office.',
            self::ToValidate => 'Nothing to approve.',
            self::Outgoing => 'Nothing waiting for the Registrar.',
            self::History => 'Nothing done yet.',
        };
    }

    /** Waiting lists oldest first; History newest first (REQ-24-02, 03). */
    public function defaultDirection(): string
    {
        return $this === self::History ? 'desc' : 'asc';
    }

    /** Incoming acts on several documents at once (REQ-24-05); Outgoing is view-only (REQ-31-05). */
    public function hasCheckboxes(): bool
    {
        return $this === self::Incoming;
    }

    /**
     * The list a request is in for one office: on its way there, to approve, waiting for the
     * Registrar, or done with (REQ-24-06).
     */
    public static function forRequest(CredentialRequest $request, int $departmentId): self
    {
        $visit = $request->openVisit;

        if ($visit === null || (int) $visit->department_id !== $departmentId) {
            return self::History;
        }

        return match ($visit->status) {
            VisitStatus::InTransit => self::Incoming,
            VisitStatus::Done => self::Outgoing,
            default => self::ToValidate,
        };
    }
}
