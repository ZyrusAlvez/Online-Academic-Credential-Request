<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;
use Locale;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;
use Throwable;

/**
 * A requester's mobile number, from any country, kept in E.164 form (`+639171234567`, REQ-36-01).
 */
class PhoneNumbers
{
    public const DEFAULT_COUNTRY = 'PH';

    /** @var array<string, string>|null */
    private static ?array $countries = null;

    /**
     * Every country a number can be from, as ISO code → "Philippines (+63)": the Philippines first,
     * then by name.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        if (self::$countries !== null) {
            return self::$countries;
        }

        $util = PhoneNumberUtil::getInstance();
        $countries = [];

        foreach ($util->getSupportedRegions() as $region) {
            $name = Locale::getDisplayRegion('-'.$region, 'en') ?: $region;
            $countries[$region] = "{$name} (+{$util->getCountryCodeForRegion($region)})";
        }

        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$countries = [self::DEFAULT_COUNTRY => $countries[self::DEFAULT_COUNTRY]] + $countries;
    }

    /** The rule for a mobile number of the given country. */
    public static function rule(string $country): Phone
    {
        return (new Phone)->country(array_key_exists($country, self::countries()) ? $country : self::DEFAULT_COUNTRY)->mobile();
    }

    /** A number valid for the country (checked by {@see rule()}), in E.164 form. */
    public static function toE164(string $number, string $country): string
    {
        return (new PhoneNumber($number, $country))->formatE164();
    }

    /**
     * A saved number back as the form's country and number, e.g. `+639171234567` → `PH`,
     * `09171234567`. Anything that isn't E.164 is given back as it is, with the default country.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(?string $saved): array
    {
        $phone = self::parse($saved);

        if ($phone === null) {
            return [self::DEFAULT_COUNTRY, (string) $saved];
        }

        $country = (string) $phone->getCountry();

        return [$country, preg_replace('/[^0-9]/', '', $phone->formatNational()) ?? ''];
    }

    /**
     * For reading: `+63 917 123 4567`; anything that isn't E.164 (a representative's number) as it is.
     */
    public static function display(?string $saved): ?string
    {
        return self::parse($saved)?->formatInternational() ?? $saved;
    }

    /** Whether a saved number can be texted: only Philippine (+63) numbers are (REQ-36-06). */
    public static function isTextable(?string $saved): bool
    {
        return $saved !== null && str_starts_with($saved, '+63');
    }

    private static function parse(?string $saved): ?PhoneNumber
    {
        if ($saved === null || ! str_starts_with($saved, '+')) {
            return null;
        }

        try {
            $phone = new PhoneNumber($saved);

            return $phone->isValid() ? $phone : null;
        } catch (Throwable) {
            return null;
        }
    }
}
