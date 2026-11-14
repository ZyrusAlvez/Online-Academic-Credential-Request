<?php

namespace App\Support;

use App\Enums\CheckItem;
use App\Models\CredentialRequest;
use App\Models\RequesterVerification;
use App\Models\User;
use BackedEnum;

/**
 * What "verified once" compares (spec 21): the details each part covers, read from a requester's
 * profile or a request's copy. A part stays verified while the details equal the copy on the request
 * it was verified on, so any edit sends it back for verification without tracking edits (REQ-21-02).
 */
final class VerifiedDetails
{
    /** The checklist parts verified once (REQ-21-01). */
    public const PARTS = [CheckItem::Identity, CheckItem::AcademicRecord];

    /**
     * The requester's form sections and the part and details each shows the badge of (REQ-21-06).
     *
     * @var array<string, array{0: CheckItem, 1: list<string>}>
     */
    public const SECTIONS = [
        'personal' => [CheckItem::Identity, ['name', 'contact_number']],
        'academic' => [CheckItem::AcademicRecord, ['academic_status', 'college_id', 'program', 'student_number', 'attended_from', 'attended_to']],
        'valid_id' => [CheckItem::Identity, ['id']],
    ];

    /**
     * The details a part covers, as plain values: a profile's saved details or a request's copy.
     *
     * @return array<string, ?string>
     */
    public static function of(CheckItem $part, User|CredentialRequest $from): array
    {
        $values = match ($part) {
            CheckItem::Identity => $from instanceof User
                ? ['name' => $from->requesterName(), 'contact_number' => $from->contact_number, 'id' => $from->latestIdentityDocument()->first()?->id]
                : ['name' => $from->requester_name, 'contact_number' => $from->contact_number, 'id' => $from->identity_document_id],
            CheckItem::AcademicRecord => [
                'academic_status' => $from->academic_status,
                'college_id' => $from->college_id,
                'program' => $from->program,
                'student_number' => $from->student_number,
                'attended_from' => $from->attended_from,
                'attended_to' => $from->attended_to,
            ],
            default => [],
        };

        return array_map(fn (mixed $value): ?string => match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        }, $values);
    }

    /**
     * Whether the details equal the verified request's copy, on every detail of the part or on the
     * given ones.
     *
     * @param  ?list<string>  $only
     */
    public static function match(CheckItem $part, User|CredentialRequest $details, CredentialRequest $verified, ?array $only = null): bool
    {
        $mine = self::of($part, $details);
        $theirs = self::of($part, $verified);

        if ($only !== null) {
            $mine = array_intersect_key($mine, array_flip($only));
            $theirs = array_intersect_key($theirs, array_flip($only));
        }

        return $mine === $theirs;
    }

    /**
     * The requester's verification of a part, whether or not their details still equal it.
     */
    public static function verification(User $user, CheckItem $part): ?RequesterVerification
    {
        return $user->verifications()->where('part', $part->value)->with('credentialRequest')->first();
    }

    /**
     * The verification of a part that the requester's saved details still equal, if any.
     */
    public static function current(User $user, CheckItem $part): ?RequesterVerification
    {
        $verification = self::verification($user, $part);

        return $verification !== null && self::match($part, $user, $verification->credentialRequest) ? $verification : null;
    }

    /**
     * Each section of the requester's form: verified or for verification (REQ-21-06).
     *
     * @return array<string, bool>
     */
    public static function sections(User $user): array
    {
        $verifications = $user->verifications()->with('credentialRequest')->get()->keyBy(fn (RequesterVerification $v) => $v->part->value);

        return array_map(function (array $section) use ($user, $verifications): bool {
            [$part, $fields] = $section;
            $verification = $verifications->get($part->value);

            return $verification !== null && self::match($part, $user, $verification->credentialRequest, $fields);
        }, self::SECTIONS);
    }
}
