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

    // Text messages to requesters (spec 36), to Philippine numbers only, through IPROG SMS. While the
    // key is blank, texts are written to the log instead (REQ-36-07).
    // The sender name is the account's approved one, set on IPROG's dashboard.
    'iprog' => [
        'token' => env('IPROG_API_TOKEN'),
    ],

    // Outside production every text goes to this one number instead, or only to the log when it is
    // blank, since demo requesters have made-up numbers that may be real (REQ-36-11).
    'sms' => [
        'redirect' => env('APP_ENV') !== 'production',
        'redirect_to' => env('SMS_REDIRECT_TO'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
