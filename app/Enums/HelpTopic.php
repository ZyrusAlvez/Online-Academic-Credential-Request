<?php

namespace App\Enums;

/**
 * The requester's help topics, in sidebar order; the value is the page's URL slug (REQ-22-01, 02).
 */
enum HelpTopic: string
{
    case GettingStarted = 'getting-started';
    case DetailsAndId = 'details-and-id';
    case Requesting = 'requesting';
    case Payment = 'payment';
    case Tracking = 'tracking';
    case RepresentativesAndRelease = 'representatives-and-release';

    public static function first(): self
    {
        return self::cases()[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::GettingStarted => 'Getting Started',
            self::DetailsAndId => 'Your Details & Valid ID',
            self::Requesting => 'Requesting a Credential',
            self::Payment => 'Payment',
            self::Tracking => 'Tracking & Statuses',
            self::RepresentativesAndRelease => 'Representatives & Release',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GettingStarted => 'What this system does, and your account.',
            self::DetailsAndId => 'Your personal and academic information and valid ID: given once, verified once.',
            self::Requesting => 'Filling in New Request, and what happens after you submit.',
            self::Payment => 'When and how to pay, and your proof of payment.',
            self::Tracking => 'Following your request, its statuses, dates and notifications.',
            self::RepresentativesAndRelease => 'Claiming your credential, yourself or through a representative.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GettingStarted => 'rocket-launch',
            self::DetailsAndId => 'identification',
            self::Requesting => 'document-plus',
            self::Payment => 'credit-card',
            self::Tracking => 'map',
            self::RepresentativesAndRelease => 'user-group',
        };
    }
}
