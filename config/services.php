<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // Sign in with Google (REQ-01-24); the button is hidden until both keys are set.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    // Text messages to requesters (spec 36): PhilSMS for +63 numbers, Telnyx for the rest. While a
    // provider's key is blank, its texts are written to the log instead (REQ-36-07).
    'philsms' => [
        'token' => env('PHILSMS_TOKEN'),
        'sender_id' => env('PHILSMS_SENDER_ID', 'PhilSMS'),
    ],

    // Outside production every text goes to this one number instead, or only to the log when it is
    // blank, since demo requesters have made-up numbers that may be real (REQ-36-11).
    'sms' => [
        'redirect' => env('APP_ENV') !== 'production',
        'redirect_to' => env('SMS_REDIRECT_TO'),
    ],

    'telnyx' => [
        'key' => env('TELNYX_API_KEY'),
        'from' => env('TELNYX_FROM', 'UPHSL'),
        'messaging_profile_id' => env('TELNYX_MESSAGING_PROFILE_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
