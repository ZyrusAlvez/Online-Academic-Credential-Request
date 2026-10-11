<?php

return [

    /*
    | URL of the university's existing online payment portal. Students pay there and
    | upload the receipt to this system; no payment is processed here.
    */
    'payment_url' => env('UPHSL_PAYMENT_URL', 'https://uphsl.edu.ph/online_payment/guest'),

    /*
    | Disk for students' payment receipts: "local" (storage/app/private/<env>) or "r2"
    | (Cloudflare R2, see docs/setup-mail-and-storage.md).
    */
    'receipts_disk' => env('RECEIPTS_DISK', 'local'),

    /*
    | The first registrar account, created when a database is set up (`db:seed`) so that
    | someone can log in and create every other account. Changing these later does not
    | change an existing password. In staging the password is always "password" (demo).
    */
    'initial_registrar' => [
        'email' => env('INITIAL_REGISTRAR_EMAIL', 'registrar@uphsl.edu.ph'),
        'password' => env('APP_ENV') === 'production' ? env('INITIAL_REGISTRAR_PASSWORD') : 'password',
    ],

];
